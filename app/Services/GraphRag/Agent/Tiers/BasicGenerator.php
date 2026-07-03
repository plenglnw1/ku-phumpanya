<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent\Tiers;

use App\Services\GraphRag\Agent\GeminiClient;
use App\Services\GraphRag\Agent\ResultFormatter;
use App\Services\GraphRag\Agent\Synthesizer;
use App\Services\GraphRag\RelationGraphBuilder;
use Illuminate\Support\Str;

/** G_B — Basic: one synthesizer call, minimal graph. */
final class BasicGenerator
{
    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly Synthesizer $synthesizer,
        private readonly RelationGraphBuilder $graphBuilder,
    ) {}

    /**
     * @param  array{query: string, docs: list<array>, relations: list<array>, topics: list<string>, entities: list<string>}  $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array
    {
        $extracted = [
            'key_points' => collect($context['docs'])->take(3)->map(
                fn (array $d): string => Str::limit((string) ($d['title'] ?? ''), 80),
            )->filter()->values()->all(),
            'entities' => array_slice($context['entities'], 0, 5),
            'courses' => collect($context['docs'])->filter(
                fn (array $d): bool => ($d['source'] ?? '') === 'KU_MOOC',
            )->take(2)->map(fn (array $d): array => [
                'title' => (string) ($d['title'] ?? ''),
                'url' => (string) ($d['url'] ?? ''),
            ])->values()->all(),
        ];

        $synth = $this->synthesizer->synthesize(
            $context['query'],
            $extracted,
            $context['relations'],
            $context['docs'],
        );

        $graph = $this->graphBuilder->build(
            array_slice($context['entities'], 0, 5),
            $context['relations'],
            15,
            20,
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
