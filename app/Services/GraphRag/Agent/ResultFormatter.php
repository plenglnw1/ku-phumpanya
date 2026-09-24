<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

use Illuminate\Support\Str;

/** Shared formatting for agent pipeline results. */
final class ResultFormatter
{
    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    public static function toEvidence(array $documents): array
    {
        return collect($documents)
            ->map(fn (array $doc): array => [
                'title' => (string) ($doc['title'] ?? 'Untitled'),
                'source' => (string) ($doc['source'] ?? 'UNKNOWN'),
                'url' => (string) ($doc['url'] ?? ''),
                'snippet' => Str::limit((string) ($doc['content'] ?? $doc['abstract'] ?? ''), 150),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $learningPath
     * @param  list<array<string, mixed>>  $fallbackPhases  Already-built phases (e.g.
     *         Synthesizer::groupIntoPhases on this query's own retrieved docs) — used
     *         as-is, never re-ranked, so the fallback is guaranteed on-topic.
     * @return array<string, mixed>
     */
    public static function normalizeLearningPath(?array $learningPath, array $fallbackPhases = []): array
    {
        if ($learningPath !== null && ! empty($learningPath['phases'] ?? $learningPath['modules'] ?? null)) {
            return [
                'estimated_hours' => (string) ($learningPath['estimated_hours'] ?? '90-140'),
                'subtitle' => (string) ($learningPath['subtitle'] ?? 'AI-ranked learning path from KU sources'),
                'phases' => $learningPath['phases'] ?? $learningPath['modules'] ?? [],
            ];
        }

        $moduleCount = array_sum(array_map(
            static fn (array $p): int => count($p['modules'] ?? []),
            $fallbackPhases,
        ));

        return [
            'estimated_hours' => $moduleCount > 0 ? ($moduleCount * 8).'-'.($moduleCount * 12) : '90-140',
            'subtitle' => 'Heuristic path from retrieved sources',
            'phases' => $fallbackPhases,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     */
    public static function docSummaryForPrompt(array $docs, int $limit = 5): string
    {
        return collect($docs)->take($limit)->map(function (array $doc, int $i): string {
            $title = $doc['title'] ?? 'Untitled';
            $source = $doc['source'] ?? '';
            $content = Str::limit((string) ($doc['content'] ?? $doc['abstract'] ?? ''), 200);

            return sprintf("[%d] %s (%s): %s", $i + 1, $title, $source, $content);
        })->implode("\n");
    }

    /**
     * @param  list<array<string, mixed>>  $relations
     */
    public static function relationsSummaryForPrompt(array $relations, int $limit = 10): string
    {
        return collect($relations)->take($limit)->map(fn (array $r): string => sprintf(
            '%s --[%s]--> %s',
            $r['subject'] ?? '?',
            $r['predicate'] ?? 'relatedTo',
            $r['object'] ?? '?',
        ))->implode("\n");
    }
}
