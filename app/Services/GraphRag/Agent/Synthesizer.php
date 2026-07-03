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

        $phases = [
            [
                'name' => 'Phase: Foundation',
                'intro' => 'Start with core concepts from retrieved KU sources.',
                'modules' => collect($extracted['courses'] ?? [])->map(fn (array $c): array => [
                    'title' => (string) ($c['title'] ?? 'Course'),
                    'hours' => '8-12 hrs',
                    'desc' => (string) ($c['url'] ?? ''),
                ])->all(),
            ],
        ];

        return [
            'title' => $title,
            'overview' => [
                'intro' => $intro,
                'analogy' => 'Like connecting dots across KU Forest papers, KUKR library, and KU MOOC courses.',
                'research_basis' => 'Grounded in '.count($docs).' retrieved documents from KU BCG corpus.',
                'expert' => 'Knowledge source: KU Phumpanya GraphRAG (local vector + relations).',
            ],
            'learning_path' => [
                'estimated_hours' => '90-140',
                'subtitle' => 'Heuristic path from retrieved sources',
                'phases' => $phases,
            ],
        ];
    }
}
