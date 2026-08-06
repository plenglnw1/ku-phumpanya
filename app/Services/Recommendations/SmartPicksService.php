<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\User;
use App\Services\GraphRag\ElasticClientFactory;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Str;

/**
 * Content-based recommender over the KU BCG corpus.
 *
 * Retrieval and ranking are deliberately separate: Elasticsearch generates a wide
 * candidate set using the user's weighted interest phrases (its Thai analyser does
 * the tokenisation, which PHP cannot do reliably), then candidates are re-scored
 * here against signals ES has no view of — topic affinity, pillar affinity, how
 * recently the user showed the interest, and whether they have already been shown
 * the document.
 *
 * Nothing is cached: the profile is rebuilt per request from `search_histories`,
 * so a new search changes the next set of picks immediately.
 */
final class SmartPicksService
{
    /** Candidates pulled before re-ranking — wide enough that diversity has room. */
    private const CANDIDATE_POOL = 60;

    /** Picks returned to the UI. */
    private const PICK_COUNT = 6;

    /** No more than this many picks may share a topic, so the list cannot repeat itself. */
    private const MAX_PER_TOPIC = 2;

    private const SOURCE_LABELS = [
        'KUKR' => 'KUKR',
        'KU_Forest' => 'KU Forest',
        'KU_MOOC' => 'KU MOOC',
    ];

    public function __construct(
        private readonly ElasticClientFactory $factory,
    ) {}

    /**
     * @return array{headline: string, reason: string, generated_at: string, items: list<array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        $profile = InterestProfile::build($user);
        $client = $this->factory->make();

        $candidates = $client instanceof Client ? $this->fetchCandidates($client, $profile) : [];
        $scored = $this->rank($candidates, $profile);
        $picks = $this->diversify($scored);

        // The raw composite is unbounded in practice, so match % is expressed
        // relative to the strongest candidate. Ordering is preserved exactly; the
        // number answers "how close to the best match is this?", not a probability.
        $best = $picks !== [] ? max(array_map(static fn (array $p): float => (float) $p['score'], $picks)) : 1.0;

        return [
            'headline' => 'AI Recommendations',
            'reason' => $this->explainStrategy($profile),
            'generated_at' => now()->toIso8601String(),
            'items' => array_map(fn (array $pick): array => $this->present($pick, $best ?: 1.0), $picks),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCandidates(Client $client, InterestProfile $profile): array
    {
        $should = [];

        foreach ($profile->topPhrases(12) as $phrase => $weight) {
            $should[] = ['multi_match' => [
                'query' => $phrase,
                'fields' => ['title^3', 'title_en^2', 'keywords^2', 'topic_names_th', 'topic_names_en', 'abstract'],
                'type' => 'best_fields',
                'boost' => round($weight, 3),
            ]];
        }

        foreach ($profile->topTopics(5) as $topic => $weight) {
            $should[] = ['match' => ['topic_names_th' => ['query' => $topic, 'boost' => round($weight * 2, 3)]]];
        }

        foreach ($profile->topPillars(3) as $pillar => $weight) {
            $should[] = ['term' => ['bcg_pillars' => ['value' => $pillar, 'boost' => round($weight, 3)]]];
        }

        // Cold start: nothing has been searched yet, so lean on the declared profile.
        foreach (array_filter([$profile->faculty, $profile->department]) as $field) {
            $should[] = ['multi_match' => [
                'query' => (string) $field,
                'fields' => ['topic_names_th^2', 'keywords', 'title'],
                'type' => 'best_fields',
                'boost' => $profile->isCold() ? 3.0 : 0.8,
            ]];
        }

        // Guarantees a non-empty pool for a brand-new account with an empty profile.
        $should[] = ['match_all' => ['boost' => 0.01]];

        try {
            $response = $client->search([
                'index' => config('elasticsearch.indices.docs'),
                'body' => [
                    'size' => self::CANDIDATE_POOL,
                    'query' => ['bool' => ['should' => $should, 'minimum_should_match' => 1]],
                ],
            ])->asArray();
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            static fn (array $hit): array => array_merge(
                ['_score' => (float) ($hit['_score'] ?? 0.0)],
                $hit['_source'] ?? [],
            ),
            $response['hits']['hits'] ?? [],
        );
    }

    /**
     * Composite re-rank. ES relevance dominates but cannot see the user's history
     * shape, so affinity and novelty adjust it.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function rank(array $candidates, InterestProfile $profile): array
    {
        if ($candidates === []) {
            return [];
        }

        $maxEs = max(array_map(static fn (array $c): float => (float) $c['_score'], $candidates)) ?: 1.0;
        $topicWeights = $profile->topics;
        $maxTopic = $topicWeights ? max($topicWeights) : 1.0;
        $pillarWeights = $profile->pillars;
        $maxPillar = $pillarWeights ? max($pillarWeights) : 1.0;
        $seen = array_flip($profile->seenUrls);

        $scored = [];

        foreach ($candidates as $doc) {
            $reasons = [];

            $esScore = (float) $doc['_score'] / $maxEs;

            // Topic affinity — how much of the user's attention sits in this subject.
            $topicAffinity = 0.0;
            $matchedTopic = null;
            foreach ((array) ($doc['topic_names_th'] ?? []) as $name) {
                foreach ($topicWeights as $topic => $weight) {
                    if ($this->looselyMatches((string) $name, (string) $topic)) {
                        $ratio = $weight / $maxTopic;
                        if ($ratio > $topicAffinity) {
                            $topicAffinity = $ratio;
                            $matchedTopic = (string) $name;
                        }
                    }
                }
            }

            $pillarAffinity = 0.0;
            $matchedPillar = null;
            foreach ((array) ($doc['bcg_pillars'] ?? []) as $pillar) {
                $weight = $pillarWeights[$pillar] ?? 0.0;
                if ($weight > 0 && $weight / $maxPillar > $pillarAffinity) {
                    $pillarAffinity = $weight / $maxPillar;
                    $matchedPillar = (string) $pillar;
                }
            }

            $profileMatch = 0.0;
            $matchedProfileField = null;
            $haystack = Str::lower(implode(' ', array_merge(
                [(string) ($doc['title'] ?? '')],
                (array) ($doc['topic_names_th'] ?? []),
                (array) ($doc['keywords'] ?? []),
            )));
            foreach (array_filter([$profile->faculty, $profile->department]) as $field) {
                if ($field !== null && Str::contains($haystack, Str::lower((string) $field))) {
                    $profileMatch = 1.0;
                    $matchedProfileField = (string) $field;
                    break;
                }
            }

            $year = (int) ($doc['year'] ?? 0);
            $recency = $year > 0 ? max(0.0, min(1.0, ($year - 2010) / 15)) : 0.3;

            $url = (string) ($doc['url'] ?? '');
            $alreadySeen = $url !== '' && isset($seen[$url]);

            $score =
                0.55 * $esScore +
                0.20 * $topicAffinity +
                0.10 * $pillarAffinity +
                0.10 * $profileMatch +
                0.05 * $recency;

            // Novelty: a document the user has already been shown is not a discovery.
            if ($alreadySeen) {
                $score *= 0.45;
                $reasons[] = ['kind' => 'seen', 'text' => 'คุณเคยเห็นเอกสารนี้แล้ว แต่ยังตรงกับความสนใจของคุณ'];
            }

            $matchedPhrase = $this->matchedPhrase($doc, $profile);
            if ($matchedPhrase !== null) {
                $count = $profile->queryCounts[$matchedPhrase] ?? 0;
                $reasons[] = [
                    'kind' => 'search',
                    'text' => $count > 1
                        ? sprintf('คุณค้นหา “%s” มาแล้ว %d ครั้ง', Str::limit($matchedPhrase, 40), $count)
                        : sprintf('ต่อยอดจากที่คุณค้นหา “%s”', Str::limit($matchedPhrase, 40)),
                ];
            }
            if ($matchedTopic !== null && $topicAffinity > 0.15) {
                $reasons[] = ['kind' => 'topic', 'text' => sprintf('อยู่ในหัวข้อ “%s” ที่คุณดูบ่อย', Str::limit($matchedTopic, 40))];
            }
            if ($matchedProfileField !== null) {
                $reasons[] = ['kind' => 'profile', 'text' => sprintf('เกี่ยวข้องกับ%sของคุณ', $matchedProfileField)];
            }
            if ($matchedPillar !== null && $pillarAffinity > 0.2) {
                $reasons[] = ['kind' => 'pillar', 'text' => sprintf('อยู่ในเสาหลัก %s ที่คุณสนใจ', $matchedPillar)];
            }
            if (($doc['source'] ?? '') === 'KU_MOOC') {
                $reasons[] = ['kind' => 'course', 'text' => 'เป็นคอร์สออนไลน์ที่เริ่มเรียนได้ทันที'];
            }
            if ($year >= (int) date('Y') - 1 && $year > 0) {
                $reasons[] = ['kind' => 'recency', 'text' => sprintf('งานวิจัยใหม่ปี %d ในหัวข้อนี้', $year)];
            }
            if (! $alreadySeen && $matchedTopic !== null) {
                $reasons[] = ['kind' => 'novel', 'text' => 'ยังไม่เคยปรากฏในผลการค้นหาของคุณ'];
            }

            $scored[] = [
                'doc' => $doc,
                'score' => $score,
                'reasons' => $reasons,
                'topic' => $matchedTopic ?? (string) (((array) ($doc['topic_names_th'] ?? []))[0] ?? ''),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Which of the user's own phrases this document answers.
     *
     * Requires real containment rather than the loose word-overlap used for scoring:
     * a shared common word like "carbon" matches most of the corpus, which produced
     * the same sentence on every card. The longest containment wins, so the reason
     * cites the most specific thing the user actually typed.
     */
    private function matchedPhrase(array $doc, InterestProfile $profile): ?string
    {
        $haystack = Str::lower(implode(' ', array_merge(
            [(string) ($doc['title'] ?? ''), (string) ($doc['title_en'] ?? '')],
            (array) ($doc['keywords'] ?? []),
            (array) ($doc['topic_names_th'] ?? []),
        )));

        $best = null;
        $bestLength = 0;

        foreach ($profile->topPhrases(12) as $phrase => $_weight) {
            $needle = Str::lower(trim((string) $phrase));
            if ($needle === '' || mb_strlen($needle) < 4) {
                continue;
            }
            if (Str::contains($haystack, $needle) && mb_strlen($needle) > $bestLength) {
                $best = (string) $phrase;
                $bestLength = mb_strlen($needle);
            }
        }

        return $best;
    }

    /**
     * Substring containment in either direction. Thai has no word boundaries, so a
     * token-based comparison would miss matches an analyser finds.
     */
    private function looselyMatches(string $a, string $b): bool
    {
        $a = Str::lower(trim($a));
        $b = Str::lower(trim($b));
        if ($a === '' || $b === '') {
            return false;
        }
        if (Str::contains($a, $b) || Str::contains($b, $a)) {
            return true;
        }

        // Fall back to shared significant words for mixed Thai/English phrases.
        $wordsA = array_filter(preg_split('/[\s,._\-()]+/u', $a) ?: [], static fn (string $w): bool => mb_strlen($w) >= 4);
        $wordsB = array_filter(preg_split('/[\s,._\-()]+/u', $b) ?: [], static fn (string $w): bool => mb_strlen($w) >= 4);

        return count(array_intersect($wordsA, $wordsB)) > 0;
    }

    /**
     * Caps how many picks may share a topic. Without this the top of the list fills
     * with near-duplicates from one subject area, which is exactly how the previous
     * mock read — the same title four times.
     *
     * @param  list<array<string, mixed>>  $scored
     * @return list<array<string, mixed>>
     */
    private function diversify(array $scored): array
    {
        $picked = [];
        $perTopic = [];
        $usedUrls = [];
        $usedTitles = [];

        foreach ($scored as $entry) {
            if (count($picked) >= self::PICK_COUNT) {
                break;
            }
            $url = (string) ($entry['doc']['url'] ?? '');
            if ($url !== '' && isset($usedUrls[$url])) {
                continue;
            }
            // The corpus holds distinct records that share a title (same work indexed
            // from several sources); showing both reads as a bug.
            $titleKey = Str::lower(trim((string) ($entry['doc']['title'] ?? '')));
            if ($titleKey !== '' && isset($usedTitles[$titleKey])) {
                continue;
            }
            $usedTitles[$titleKey] = true;
            $topic = $entry['topic'] !== '' ? $entry['topic'] : '__none__';
            if (($perTopic[$topic] ?? 0) >= self::MAX_PER_TOPIC) {
                continue;
            }
            $perTopic[$topic] = ($perTopic[$topic] ?? 0) + 1;
            $usedUrls[$url] = true;
            $picked[] = $entry;
        }

        return $picked;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(array $entry, float $bestScore): array
    {
        $doc = $entry['doc'];
        $source = (string) ($doc['source'] ?? '');
        $topic = (string) (((array) ($doc['topic_names_th'] ?? []))[0] ?? '');
        $year = (string) ($doc['year'] ?? '');
        $keywords = array_values(array_filter(array_map(
            static fn (mixed $k): string => trim((string) $k),
            (array) ($doc['keywords'] ?? []),
        )));

        // Only facts the document actually carries — no invented durations or levels.
        $info = implode(' · ', array_filter([
            $year !== '' ? $year : null,
            $topic !== '' ? Str::limit($topic, 38) : null,
            $keywords !== [] ? sprintf('%d คำสำคัญ', count($keywords)) : null,
        ]));

        $reasons = array_map(static fn (array $r): string => $r['text'], array_slice($entry['reasons'], 0, 2));

        return [
            'title' => (string) ($doc['title'] ?? 'ไม่มีชื่อเรื่อง'),
            'info' => $info,
            'source' => self::SOURCE_LABELS[$source] ?? ($source !== '' ? $source : 'KU BCG'),
            'match' => (int) round(max(45, min(98, ($entry['score'] / $bestScore) * 100))),
            'reasons' => $reasons,
            'keywords' => array_slice($keywords, 0, 4),
            'url' => (string) ($doc['url'] ?? ''),
            'query' => (string) ($doc['title'] ?? ''),
        ];
    }

    private function explainStrategy(InterestProfile $profile): string
    {
        if ($profile->isCold()) {
            $field = $profile->faculty ?? $profile->department;

            return $field
                ? sprintf('ยังไม่มีประวัติการค้นหา — แนะนำจากโปรไฟล์ %s ของคุณ', $field)
                : 'ยังไม่มีประวัติการค้นหา — เริ่มค้นหาเพื่อรับคำแนะนำที่ตรงกับคุณมากขึ้น';
        }

        // Cite the user's own most recent query rather than the highest-weighted
        // topic: the topic is an internal aggregate and naming it can point at a
        // subject none of the visible picks belong to.
        $latest = array_key_first($profile->queryCounts);
        if ($latest !== null) {
            return sprintf(
                'อ้างอิงจาก %d การค้นหาล่าสุด ล่าสุดคือ “%s”',
                $profile->searchCount,
                Str::limit((string) $latest, 40),
            );
        }

        return sprintf('อ้างอิงจาก %d การค้นหาล่าสุดของคุณ', $profile->searchCount);
    }
}
