<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

use App\Services\GraphRag\Agent\Tiers\AdvancedGenerator;
use App\Services\GraphRag\Agent\Tiers\BasicGenerator;
use App\Services\GraphRag\Agent\Tiers\IntermediateGenerator;
use App\Services\GraphRag\RetrievalLinker;

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
    ) {}

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

        return array_merge($result, [
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
