<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent\Tiers;

use App\Services\GraphRag\Agent\ResultFormatter;
use App\Services\GraphRag\Agent\SubAgentExtractor;
use App\Services\GraphRag\Agent\Synthesizer;
use App\Services\GraphRag\RelationGraphBuilder;

/** G_M — Intermediate: one sub-agent + synthesizer + medium graph. */
final class IntermediateGenerator
{
    public function __construct(
        private readonly SubAgentExtractor $extractor,
        private readonly Synthesizer $synthesizer,
        private readonly RelationGraphBuilder $graphBuilder,
    ) {}

    /**
     * @param  array{query: string, docs: list<array>, relations: list<array>, topics: list<string>, entities: list<string>}  $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array
    {
        $extracted = $this->extractor->extract(
            $context['query'],
            $context['docs'],
            $context['relations'],
            'multi-source synthesis',
        );

        $synth = $this->synthesizer->synthesize(
            $context['query'],
            $extracted,
            $context['relations'],
            $context['docs'],
        );

        $graph = $this->graphBuilder->build(
            array_merge($extracted['entities'], $context['entities']),
            $context['relations'],
            25,
            40,
        );

        return [
            'title' => $synth['title'],
            'overview' => $synth['overview'],
            'knowledge_graph' => $graph,
            'learning_path' => ResultFormatter::normalizeLearningPath($synth['learning_path']),
            'evidence' => ResultFormatter::toEvidence($context['docs']),
        ];
    }
}
