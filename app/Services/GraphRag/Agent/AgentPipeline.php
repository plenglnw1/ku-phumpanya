<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

use App\Services\GraphRag\Agent\Tiers\AdvancedGenerator;
use App\Services\GraphRag\Agent\Tiers\BasicGenerator;
use App\Services\GraphRag\Agent\Tiers\IntermediateGenerator;
use App\Services\GraphRag\RelationGraphBuilder;
use App\Services\GraphRag\RetrievalLinker;
use Illuminate\Support\Str;

/**
 * EllieSQL-style 3-phase pipeline:
 * I) Retrieval Linking → II) Router → III) Tiered generation (G_B / G_M / G_A)
 */
final class AgentPipeline
{
    public function __construct(
        private readonly RetrievalLinker $linker,
        private readonly QueryRouter $router,
        private readonly BasicGenerator $basic,
        private readonly IntermediateGenerator $intermediate,
        private readonly AdvancedGenerator $advanced,
        private readonly GeminiClient $gemini,
        private readonly Synthesizer $synthesizer,
        private readonly RelationGraphBuilder $graphBuilder,
    ) {}

    /**
     * Retrieval-only result with no Gemini call: same shape as run(), marked
     * status "pending" so the caller knows the synthesis is still to come.
     *
     * @return array<string, mixed>
     */
    public function draft(string $query): array
    {
        $retrievalStart = microtime(true);
        $context = $this->linker->link($query);
        $retrievalMs = (int) round((microtime(true) - $retrievalStart) * 1000);

        $docs = $context['docs'];
        $extracted = [
            'key_points' => collect($docs)->take(3)->map(
                fn (array $d): string => Str::limit((string) ($d['title'] ?? ''), 80),
            )->filter()->values()->all(),
            'entities' => array_slice($context['entities'], 0, 5),
            'courses' => [],
        ];
        $heuristic = $this->synthesizer->fallbackSynthesize(
            $query,
            $extracted,
            $docs,
            (string) ($docs[0]['title'] ?? Str::title($query)),
        );

        return [
            'title' => $heuristic['title'],
            'overview' => $heuristic['overview'],
            'knowledge_graph' => $this->graphBuilder->build(array_slice($context['entities'], 0, 5), $context['relations'], (int) config('knowledge_graph.limits.basic.nodes', 15), (int) config('knowledge_graph.limits.basic.edges', 20)),
            'learning_path' => ResultFormatter::normalizeLearningPath(null, $this->synthesizer->groupIntoPhases($docs)),
            'evidence' => ResultFormatter::toEvidence($docs),
            'tags' => ResultFormatter::tags($docs, config('bcg_topics.faculties', [])),
            'status' => 'pending',
            '_meta' => [
                'docs_retrieved' => count($docs),
                'relations_retrieved' => count($context['relations']),
                'sub_queries' => [],
                'timing' => ['retrieval_ms' => $retrievalMs, 'synthesis_ms' => 0],
            ],
        ];
    }

    /**
     * @return array{title: string, overview: array, knowledge_graph: array, learning_path: array, evidence: list, tier: string, _meta: array}
     */
    public function run(string $query): array
    {
        $this->gemini->resetCallCount();

        $retrievalStart = microtime(true);
        $context = $this->linker->link($query);
        $retrievalMs = (int) round((microtime(true) - $retrievalStart) * 1000);

        // Gemini's own learning_path.phases is sometimes empty (schema allows it),
        // in which case every tier generator falls through to normalizeLearningPath's
        // fallback branch. Compute that fallback once, here, from the docs actually
        // retrieved for this query — so it's never missing and always on-topic.
        $context['fallback_phases'] = $this->synthesizer->groupIntoPhases($context['docs']);

        $route = $this->router->route($context);
        $tier = $route['tier'];

        $synthesisStart = microtime(true);
        $result = match ($tier) {
            'intermediate' => $this->intermediate->generate($context),
            'advanced' => $this->advanced->generate($context),
            default => $this->basic->generate($context),
        };
        $synthesisMs = (int) round((microtime(true) - $synthesisStart) * 1000);

        $subQueries = $result['_sub_queries'] ?? [];
        unset($result['_sub_queries']);
        $result['learning_path'] = ResultFormatter::withDocumentFields($result['learning_path'] ?? [], $context['docs']);

        return array_merge($result, [
            'tags' => ResultFormatter::tags($context['docs'], config('bcg_topics.faculties', [])),
            'status' => 'ready',
            'tier' => $tier,
            '_meta' => [
                'reason' => $route['reason'],
                'calls' => $this->gemini->getCallCount(),
                'models' => config('gemini.models'),
                'docs_retrieved' => count($context['docs']),
                'relations_retrieved' => count($context['relations']),
                'sub_queries' => $subQueries,
                'timing' => [
                    'retrieval_ms' => $retrievalMs,
                    'synthesis_ms' => $synthesisMs,
                ],
            ],
        ]);
    }
}
