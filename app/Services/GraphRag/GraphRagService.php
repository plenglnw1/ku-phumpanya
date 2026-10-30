<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use App\Services\GraphRag\Agent\AgentPipeline;
use App\Services\GraphRag\Agent\ResultFormatter;
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
     * @return array{title: string, overview: array<string, string>, knowledge_graph: array<string, mixed>, learning_path: array<string, mixed>, evidence: list<array<string, mixed>>, tags: array{bcg_pillars: list<string>, faculties: list<string>}, tier?: string, _meta?: array<string, mixed>}
     */
    public function search(string $query): array
    {
        if (config('gemini.enabled')) {
            return $this->agentPipeline->run($query);
        }

        return $this->legacySearch($query);
    }

    public function synthesizesAsync(): bool
    {
        return (bool) config('gemini.enabled') && (bool) config('gemini.async_synthesis');
    }

    /**
     * Retrieval-only result (status "pending") to show while search() synthesizes.
     *
     * @return array<string, mixed>
     */
    public function draft(string $query): array
    {
        return $this->agentPipeline->draft($query);
    }

    /**
     * @return array{title: string, overview: array<string, string>, knowledge_graph: array<string, mixed>, learning_path: array<string, mixed>, evidence: list<array<string, mixed>>, _meta: array<string, mixed>}
     */
    private function legacySearch(string $query): array
    {
        $retrievalStart = microtime(true);
        $documents = $this->retriever->retrieve($query, 6);
        $retrievalMs = (int) round((microtime(true) - $retrievalStart) * 1000);

        $synthesisStart = microtime(true);
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

        $content = ResultFormatter::text($topDocument['content'] ?? $topDocument['abstract'] ?? '') ?: 'ไม่มีบทสรุป';
        $synthesisMs = (int) round((microtime(true) - $synthesisStart) * 1000);

        return [
            'title' => $title,
            'overview' => [
                'intro' => $content,
                'analogy' => 'กราฟความรู้เชื่อมแนวคิดกับแหล่งข้อมูล คำตอบจึงอ้างอิงได้ทั้งงานวิจัย KUKR โครงการ KU Forest และคอร์สของ มก.',
                'research_basis' => 'อ้างอิงจากคลังข้อมูล KU BCG และความสัมพันธ์ข้ามแหล่งข้อมูล',
                'expert' => 'แหล่งความรู้: KU Phumpanya GraphRAG',
            ],
            'knowledge_graph' => $graph,
            'learning_path' => $this->toLearningPath($paths),
            'evidence' => ResultFormatter::toEvidence($documents),
            'tags' => ResultFormatter::tags($documents, config('bcg_topics.faculties', [])),
            '_meta' => [
                'docs_retrieved' => count($documents),
                'relations_retrieved' => 0,
                'sub_queries' => [],
                'timing' => [
                    'retrieval_ms' => $retrievalMs,
                    'synthesis_ms' => $synthesisMs,
                ],
            ],
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
                'hours' => '',
                'desc' => (string) ($course['url'] ?? ''),
            ])->all();

            return [
                'name' => (string) $path['title'],
                'intro' => (string) ($path['description'] ?? ''),
                'modules' => $courses,
            ];
        })->all();

        return [
            'estimated_hours' => '',
            'subtitle' => 'จัดลำดับจากความใกล้เคียงของคำค้น เสาหลัก BCG และคณะที่เกี่ยวข้อง',
            'phases' => $modules,
        ];
    }
}
