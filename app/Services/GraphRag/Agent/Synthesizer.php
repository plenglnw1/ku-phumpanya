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
    private function fallbackSynthesize(string $query, array $extracted, array $docs, string $title): array
    {
        $intro = implode(' ', $extracted['key_points'] ?? [])
            ?: (string) ($docs[0]['content'] ?? $docs[0]['abstract'] ?? 'No summary available.');

        $phases = $this->groupIntoPhases($docs);
        $moduleCount = array_sum(array_map(static fn (array $p): int => count($p['modules']), $phases));

        return [
            'title' => $title,
            'overview' => [
                'intro' => $intro,
                'analogy' => 'Like connecting dots across KU Forest papers, KUKR library, and KU MOOC courses.',
                'research_basis' => 'Grounded in '.count($docs).' retrieved documents from KU BCG corpus.',
                'expert' => 'Knowledge source: KU Phumpanya GraphRAG (local vector + relations).',
            ],
            'learning_path' => [
                // Derived from the per-module 8-12 hrs rather than a fixed constant, so the
                // headline total stays consistent with however many modules were built.
                'estimated_hours' => $moduleCount > 0 ? ($moduleCount * 8).'-'.($moduleCount * 12) : '0',
                'subtitle' => 'Heuristic path from retrieved sources',
                'phases' => $phases,
            ],
        ];
    }

    /**
     * Partitions the retrieved documents into phases by subject area.
     *
     * Every document carries `topic_names_th`, so a partition can never leave a phase
     * empty — unlike filtering against fixed categories (e.g. KU MOOC, which is 6 of
     * 612 documents and absent from most result sets). Groups keep retrieval order, so
     * the phase holding the best-matching document comes first.
     *
     * Note this is a *thematic* ordering, not a prerequisite one: the corpus records
     * carry no dependency relations. Real sequencing needs the synthesizer LLM.
     *
     * @param  list<array<string, mixed>>  $docs
     * @return list<array<string, mixed>>
     */
    private function groupIntoPhases(array $docs, int $maxPhases = 3, int $maxModules = 4): array
    {
        $groups = [];
        foreach ($docs as $doc) {
            $key = $this->topicLabel($doc);
            $groups[$key] ??= [];
            $groups[$key][] = $doc;
        }

        $selected = collect($groups)->take($maxPhases);

        // A query whose results all share one subject area yields a single phase. Widen
        // it so the retrieved documents aren't discarded just because they didn't split.
        $perPhase = $selected->count() === 1 ? $maxPhases * $maxModules : $maxModules;

        return $selected
            ->map(function (array $group, string $name) use ($perPhase): array {
                $shown = collect($group)->take($perPhase);

                return [
                    'name' => $name,
                    'intro' => $this->phaseIntro($shown->all()),
                    // abstract/authors/year/source are carried through so the topic detail
                    // page can render per-document content instead of repeating the
                    // result-wide overview on every card.
                    'modules' => $shown->map(fn (array $d): array => [
                        'title' => (string) ($d['title'] ?? 'Untitled'),
                        'hours' => '8-12 hrs',
                        'desc' => $this->moduleDesc($d),
                        'url' => (string) ($d['url'] ?? ''),
                        'abstract' => trim((string) ($d['abstract'] ?? $d['content'] ?? '')),
                        'authors' => $this->authorList($d),
                        'year' => (string) ($d['year'] ?? ''),
                        'source' => (string) ($d['source'] ?? ''),
                        'keywords' => collect((array) ($d['keywords'] ?? []))
                            ->map(fn (mixed $k): string => trim((string) $k))
                            ->filter()->take(8)->values()->all(),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
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
            ->map(fn (mixed $a): string => trim((string) $a))
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
            $first = trim((string) (reset($names) ?: ''));
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
        $abstract = trim((string) ($doc['abstract'] ?? $doc['content'] ?? ''));
        if ($abstract !== '') {
            return Str::limit($abstract, 120);
        }

        $keywords = collect((array) ($doc['keywords'] ?? []))
            ->map(fn (mixed $k): string => trim((string) $k))
            ->filter()
            ->take(5);
        if ($keywords->isNotEmpty()) {
            return $keywords->implode(', ');
        }

        $authors = (array) ($doc['authors'] ?? []);
        $author = trim((string) (reset($authors) ?: ($doc['authors'] ?? '')));
        $year = trim((string) ($doc['year'] ?? ''));

        return trim($author.($author !== '' && $year !== '' ? ' · ' : '').$year) ?: 'ไม่มีคำอธิบาย';
    }
}
