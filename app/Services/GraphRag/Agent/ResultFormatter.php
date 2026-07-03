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
     * @param  list<array<string, mixed>>  $fallbackPaths
     * @return array<string, mixed>
     */
    public static function normalizeLearningPath(?array $learningPath, array $fallbackPaths = []): array
    {
        if ($learningPath !== null && ! empty($learningPath['phases'] ?? $learningPath['modules'] ?? null)) {
            return [
                'estimated_hours' => (string) ($learningPath['estimated_hours'] ?? '90-140'),
                'subtitle' => (string) ($learningPath['subtitle'] ?? 'AI-ranked learning path from KU sources'),
                'phases' => $learningPath['phases'] ?? $learningPath['modules'] ?? [],
            ];
        }

        $modules = collect($fallbackPaths)->map(fn (array $path): array => [
            'name' => 'Phase: '.($path['title'] ?? 'Module'),
            'intro' => (string) ($path['description'] ?? $path['summary'] ?? ''),
            'modules' => collect($path['courses'] ?? [])->map(fn (array $c): array => [
                'title' => (string) ($c['title'] ?? 'Course'),
                'hours' => '8-12 hrs',
                'desc' => (string) ($c['url'] ?? ''),
            ])->all(),
        ])->all();

        return [
            'estimated_hours' => '90-140',
            'subtitle' => 'Ranked by BCG tags + faculty overlap',
            'phases' => $modules,
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
