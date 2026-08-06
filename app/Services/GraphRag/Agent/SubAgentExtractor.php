<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

/** G_M / G_A sub-agent — flash-lite structured extraction. */
final class SubAgentExtractor
{
    public function __construct(
        private readonly GeminiClient $gemini,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $docs
     * @param  list<array<string, mixed>>  $relations
     * @return array{key_points: list<string>, entities: list<string>, courses: list<array<string, string>>}
     */
    public function extract(string $query, array $docs, array $relations, string $focus = ''): array
    {
        $fallback = $this->fallbackExtract($docs, $relations);

        if (! config('gemini.enabled') || config('gemini.api_key') === '') {
            return $fallback;
        }

        $prompt = sprintf(
            "Extract structured facts for KU BCG query.\nQuery: %s\nFocus: %s\n\nDocuments:\n%s\n\nRelations:\n%s\n\nReturn JSON: key_points (3-5 bullets), entities (names), courses (title+url from KU_MOOC if any).",
            $query,
            $focus !== '' ? $focus : 'general',
            ResultFormatter::docSummaryForPrompt($docs, 4),
            ResultFormatter::relationsSummaryForPrompt($relations, 6),
        );

        $schema = [
            'type' => 'object',
            'properties' => [
                'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                'entities' => ['type' => 'array', 'items' => ['type' => 'string']],
                'courses' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'required' => ['key_points', 'entities', 'courses'],
        ];

        $result = $this->gemini->generateJson(
            (string) config('gemini.models.sub'),
            $prompt,
            $schema,
        );

        if ($result === null) {
            return $fallback;
        }

        return [
            'key_points' => array_values($result['key_points'] ?? $fallback['key_points']),
            'entities' => array_values($result['entities'] ?? $fallback['entities']),
            'courses' => array_values($result['courses'] ?? $fallback['courses']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @param  list<array<string, mixed>>  $relations
     * @return array{key_points: list<string>, entities: list<string>, courses: list<array<string, string>>}
     */
    private function fallbackExtract(array $docs, array $relations): array
    {
        return [
            'key_points' => collect($docs)->take(3)->map(fn (array $d): string => (string) ($d['title'] ?? ''))->filter()->values()->all(),
            'entities' => collect($relations)->flatMap(fn (array $r): array => [$r['subject'] ?? '', $r['object'] ?? ''])->filter()->unique()->take(8)->values()->all(),
            'courses' => $this->fallbackCourses($docs),
        ];
    }

    /**
     * KU MOOC entries are the only true courses, but the corpus holds 6 of them
     * across 612 documents — most queries retrieve none. Filtering on MOOC alone
     * therefore yielded an empty learning path. Rank MOOC first, then top up with
     * the highest-scoring retrieved documents so the phase always has content.
     *
     * @param  list<array<string, mixed>>  $docs
     * @return list<array<string, string>>
     */
    private function fallbackCourses(array $docs): array
    {
        $isMooc = fn (array $d): bool => ($d['source'] ?? '') === 'KU_MOOC';

        return collect($docs)->filter($isMooc)
            ->concat(collect($docs)->reject($isMooc))
            ->take(4)
            ->map(fn (array $d): array => [
                'title' => (string) ($d['title'] ?? 'Course'),
                'url' => (string) ($d['url'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
