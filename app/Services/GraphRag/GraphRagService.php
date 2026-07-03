<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use App\Services\GraphRag\Agent\AgentPipeline;
use Illuminate\Support\Str;

final class GraphRagService
{
    public function __construct(
        private readonly HybridRetriever $retriever,
        private readonly SubgraphBuilder $subgraphBuilder,
        private readonly LearningPathwayRanker $pathwayRanker,
        private readonly AgentPipeline $agentPipeline,
    ) {}

    /**
     * @return list<string>
     */
    public function suggestions(): array
    {
        return config('graphrag_seed.suggestions', []);
    }

    /**
     * @return array{title: string, overview: array<string, string>, knowledge_graph: array<string, mixed>, learning_path: array<string, mixed>, evidence: list<array<string, mixed>>, tier?: string, _meta?: array<string, mixed>}
     */
    public function search(string $query): array
    {
        if (config('gemini.enabled')) {
            return $this->agentPipeline->run($query);
        }

        return $this->legacySearch($query);
    }

    /**
     * @return array{title: string, overview: array<string, string>, knowledge_graph: array<string, mixed>, learning_path: array<string, mixed>, evidence: list<array<string, mixed>>}
     */
    private function legacySearch(string $query): array
    {
        $documents = $this->retriever->retrieve($query, 6);
        $topDocument = $documents[0] ?? [];
        $title = (string) ($topDocument['title'] ?? Str::title($query));

        $bcgTags = collect($documents)
            ->flatMap(fn (array $doc): array => $doc['bcg_tags'] ?? $doc['bcg_pillars'] ?? [])
            ->unique()
            ->values()
            ->all();

        $facultyTags = collect($documents)
            ->flatMap(fn (array $doc): array => $doc['faculty_tags'] ?? $doc['faculties'] ?? [])
            ->unique()
            ->values()
            ->all();

        $entities = array_values(array_unique(array_filter(array_merge([$title], $bcgTags, $facultyTags))));

        $graph = $this->subgraphBuilder->build($entities, 40, 80);
        $tokens = collect(explode(' ', Str::lower($query)))->filter()->values()->all();
        $paths = $this->pathwayRanker->rank($tokens, $bcgTags, $facultyTags, 3);

        $content = (string) ($topDocument['content'] ?? $topDocument['abstract'] ?? 'No contextual summary found.');

        return [
            'title' => $title,
            'overview' => [
                'intro' => $content,
                'analogy' => 'GraphRAG links entities and sources so answers are grounded across KU Forest, KUKR, and KU MOOC.',
                'research_basis' => 'Seeded from KU BCG corpus with cross-source triplets.',
                'expert' => 'Knowledge source: KU integrated prototype (GraphRAG Simple).',
            ],
            'knowledge_graph' => $graph,
            'learning_path' => $this->toLearningPath($paths),
            'evidence' => $this->toEvidence($documents),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $paths
     * @return array<string, mixed>
     */
    private function toLearningPath(array $paths): array
    {
        $modules = collect($paths)->map(function (array $path): array {
            $courses = collect($path['courses'] ?? [])->map(fn (array $course): array => [
                'title' => (string) ($course['title'] ?? 'Course'),
                'hours' => '8-12 hrs',
                'desc' => (string) ($course['url'] ?? ''),
            ])->all();

            return [
                'name' => 'Phase: '.$path['title'],
                'intro' => (string) ($path['description'] ?? ''),
                'modules' => $courses,
            ];
        })->all();

        return [
            'estimated_hours' => '90-140',
            'subtitle' => 'Ranked by lexical similarity + BCG tags + faculty overlap',
            'phases' => $modules,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    private function toEvidence(array $documents): array
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
}
