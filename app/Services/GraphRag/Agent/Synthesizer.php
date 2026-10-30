<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

use Illuminate\Support\Str;

/** Final synthesizer — flash model merges sub-agent output into overview + learning path. */
final class Synthesizer
{
    public function __construct(
        private readonly GeminiClient $gemini,
    ) {}

    /**
     * @param  array{key_points: list<string>, entities: list<string>, courses: list<array>}  $extracted
     * @param  list<array<string, mixed>>  $relations
     * @return array{overview: array<string, string>, learning_path: array<string, mixed>, title: string}
     */
    public function synthesize(string $query, array $extracted, array $relations, array $docs): array
    {
        $title = (string) ($docs[0]['title'] ?? Str::title($query));
        $fallback = $this->fallbackSynthesize($query, $extracted, $docs, $title);

        if (! config('gemini.enabled') || config('gemini.api_key') === '') {
            return $fallback;
        }

        $prompt = sprintf(
            "Synthesize KU BCG answer for Thai/English academic audience.\nQuery: %s\n\nKey points:\n%s\n\nEntities: %s\n\nRelations:\n%s\n\nCourses: %s\n\nReturn JSON with title, overview (intro, analogy, research_basis, expert), learning_path (estimated_hours, subtitle, phases array with name, intro, modules[{title,hours,desc}]).",
            $query,
            implode("\n- ", $extracted['key_points'] ?? []),
            implode(', ', $extracted['entities'] ?? []),
            ResultFormatter::relationsSummaryForPrompt($relations, 8),
            json_encode($extracted['courses'] ?? [], JSON_UNESCAPED_UNICODE),
        );

        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'overview' => [
                    'type' => 'object',
                    'properties' => [
                        'intro' => ['type' => 'string'],
                        'analogy' => ['type' => 'string'],
                        'research_basis' => ['type' => 'string'],
                        'expert' => ['type' => 'string'],
                    ],
                    'required' => ['intro', 'analogy', 'research_basis', 'expert'],
                ],
                'learning_path' => ['type' => 'object'],
            ],
            'required' => ['title', 'overview', 'learning_path'],
        ];

        $result = $this->gemini->generateJson(
            (string) config('gemini.models.synth'),
            $prompt,
            $schema,
        );

        if ($result === null) {
            return $fallback;
        }

        return [
            'title' => (string) ($result['title'] ?? $title),
            'overview' => [
                'intro' => (string) ($result['overview']['intro'] ?? $fallback['overview']['intro']),
                'analogy' => (string) ($result['overview']['analogy'] ?? $fallback['overview']['analogy']),
                'research_basis' => (string) ($result['overview']['research_basis'] ?? $fallback['overview']['research_basis']),
                'expert' => (string) ($result['overview']['expert'] ?? $fallback['overview']['expert']),
            ],
            'learning_path' => is_array($result['learning_path'] ?? null)
                ? $result['learning_path']
                : $fallback['learning_path'],
        ];
    }

    /**
     * @param  array{key_points: list<string>, entities: list<string>, courses: list<array>}  $extracted
     * @param  list<array<string, mixed>>  $docs
     * @return array{overview: array<string, string>, learning_path: array<string, mixed>, title: string}
     */
    public function fallbackSynthesize(string $query, array $extracted, array $docs, string $title): array
    {
        $intro = implode(' ', $extracted['key_points'] ?? [])
            ?: (ResultFormatter::text($docs[0]['content'] ?? $docs[0]['abstract'] ?? '') ?: 'ไม่มีบทสรุป');

        return [
            'title' => $title,
            'overview' => [
                'intro' => $intro,
                'analogy' => 'เหมือนการลากเส้นเชื่อมจุดระหว่างงานวิจัย KUKR โครงการ KU Forest และคอร์สของ มก.',
                'research_basis' => 'อ้างอิงจากเอกสาร '.count($docs).' รายการที่ค้นพบในคลังข้อมูล KU BCG',
                'expert' => 'แหล่งความรู้: KU Phumpanya GraphRAG (เอกสารและความสัมพันธ์ในคลังข้อมูล)',
            ],
            'learning_path' => [
                // No source publishes study hours, so none are invented.
                'estimated_hours' => '',
                'subtitle' => ResultFormatter::SUBTITLE_HEURISTIC,
                'phases' => $this->groupIntoPhases($docs),
            ],
        ];
    }

    /**
     * Partitions the retrieved documents into phases.
     *
     * Courses that carry `teaches`/`needs` and depend on one another come first, one
     * phase per prerequisite depth (see LearningUnitOrder). Everything else is grouped
     * by subject area: every document carries `topic_names_th`, so a partition can
     * never leave a phase empty. Groups keep retrieval order, so the phase holding the
     * best-matching document comes first. That part is a *thematic* ordering, not a
     * prerequisite one.
     *
     * @param  list<array<string, mixed>>  $docs
     * @return list<array<string, mixed>>
     */
    public function groupIntoPhases(array $docs, int $maxPhases = 3, int $maxModules = 4): array
    {
        $units = array_values(array_filter($docs, LearningUnitOrder::isUnit(...)));
        $order = LearningUnitOrder::order($units);

        $phases = [];
        if ($order['ordered']) {
            foreach (array_slice($order['phases'], 0, $maxPhases) as $i => $phase) {
                $shown = array_slice($phase, 0, $maxModules);
                $phases[] = $this->phase('ขั้นที่ '.($i + 1).' · '.$this->stageLabel($i), $shown);
            }
            $docs = array_values(array_filter($docs, fn (array $d): bool => ! LearningUnitOrder::isUnit($d)));
        }

        $groups = [];
        foreach ($docs as $doc) {
            $groups[$this->topicLabel($doc)][] = $doc;
        }

        $selected = collect($groups)->take($maxPhases - count($phases));

        // A query whose results all share one subject area yields a single phase. Widen
        // it so the retrieved documents aren't discarded just because they didn't split.
        $perPhase = $phases === [] && $selected->count() === 1 ? $maxPhases * $maxModules : $maxModules;

        foreach ($selected as $name => $group) {
            $phases[] = $this->phase((string) $name, array_slice($group, 0, $perPhase));
        }

        return $phases;
    }

    /**
     * @param  list<array<string, mixed>>  $shown
     * @return array<string, mixed>
     */
    private function phase(string $name, array $shown): array
    {
        return [
            'name' => $name,
            'intro' => $this->phaseIntro($shown),
            // abstract/authors/year/source are carried through so the topic detail
            // page can render per-document content instead of repeating the
            // result-wide overview on every card.
            'modules' => array_map(fn (array $d): array => [
                'title' => (string) ($d['title'] ?? 'Untitled'),
                'hours' => '',
                'desc' => $this->moduleDesc($d),
                'url' => (string) ($d['url'] ?? ''),
                'abstract' => self::text($d['abstract'] ?? $d['content'] ?? ''),
                'authors' => $this->authorList($d),
                'year' => self::text($d['year'] ?? ''),
                'source' => (string) ($d['source'] ?? ''),
                ...ResultFormatter::keywords($d, 8),
                ...ResultFormatter::aiTranslation($d),
                ...LearningUnitOrder::moduleFields($d),
                // KU Forest publishes no abstract, so these Thai project
                // details are all the detail page has to show for those
                // documents. Empty on sources that do not carry them.
                'category' => (string) ($d['category'] ?? ''),
                'project_nature' => (string) ($d['project_nature'] ?? ''),
                'budget_year' => (string) ($d['budget_year'] ?? ''),
                'funding' => (string) ($d['funding'] ?? ''),
                'department' => (string) ($d['department'] ?? ''),
            ], array_values($shown)),
        ];
    }

    private function stageLabel(int $depth): string
    {
        return match ($depth) {
            0 => 'ปูพื้นฐาน',
            1 => 'ต่อยอด',
            default => 'ขั้นสูง',
        };
    }

    /**
     * `authors` arrives as a list on some records and a single string on others.
     *
     * @param  array<string, mixed>  $doc
     * @return list<string>
     */
    private function authorList(array $doc): array
    {
        $raw = $doc['authors'] ?? [];

        return collect(is_array($raw) ? $raw : [$raw])
            ->map(fn (mixed $a): string => self::text($a))
            ->filter()
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    private function topicLabel(array $doc): string
    {
        foreach (['topic_names_th', 'topic_names_en'] as $field) {
            $names = (array) ($doc[$field] ?? []);
            $first = self::text(reset($names) ?: '');
            if ($first !== '') {
                return $first;
            }
        }

        return 'แหล่งข้อมูลที่เกี่ยวข้อง';
    }

    /**
     * @param  list<array<string, mixed>>  $group
     */
    private function phaseIntro(array $group): string
    {
        $bySource = collect($group)->countBy(fn (array $d): string => (string) ($d['source'] ?? 'UNKNOWN'));
        $labels = [
            'KUKR' => 'งานวิจัย KUKR',
            'KU_Forest' => 'โครงการวิจัย KU Forest',
            'KU_MOOC' => 'คอร์สออนไลน์ KU MOOC',
            'KU_NonDegree' => 'ชุดวิชา Non-Degree',
        ];

        return $bySource
            ->map(fn (int $n, string $source): string => $n.' '.($labels[$source] ?? $source))
            ->values()
            ->implode(' · ');
    }

    /**
     * Abstracts exist on 171 of 612 documents (none on KU Forest), so fall through to
     * keywords, then to attribution — never to an empty card body.
     *
     * @param  array<string, mixed>  $doc
     */
    private function moduleDesc(array $doc): string
    {
        $abstract = self::text($doc['abstract'] ?? $doc['content'] ?? '');
        if ($abstract !== '') {
            return Str::limit($abstract, 120);
        }

        $keywords = collect((array) ($doc['keywords'] ?? []))
            ->map(fn (mixed $k): string => self::text($k))
            ->filter()
            ->take(5);
        if ($keywords->isNotEmpty()) {
            return $keywords->implode(', ');
        }

        $authors = (array) ($doc['authors'] ?? []);
        $author = self::text(reset($authors) ?: '');
        $year = self::text($doc['year'] ?? '');

        return trim($author.($author !== '' && $year !== '' ? ' · ' : '').$year) ?: 'ไม่มีคำอธิบาย';
    }

    private static function text(mixed $value): string
    {
        return ResultFormatter::text($value);
    }
}
