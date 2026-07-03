<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent\Tiers;

use App\Services\GraphRag\Agent\ResultFormatter;
use App\Services\GraphRag\Agent\SubAgentExtractor;
use App\Services\GraphRag\Agent\Synthesizer;
use App\Services\GraphRag\RelationGraphBuilder;
use Illuminate\Support\Str;

/** G_A — Advanced: fan-out sub-agents per topic + full graph + synthesizer. */
final class AdvancedGenerator
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
        $topicGroups = $this->groupDocsByTopic($context['docs']);
        $merged = [
            'key_points' => [],
            'entities' => [],
            'courses' => [],
        ];

        foreach ($topicGroups as $topicId => $docs) {
            $slice = $this->extractor->extract(
                $context['query'],
                $docs,
                $context['relations'],
                "topic {$topicId}",
            );
            $merged['key_points'] = array_merge($merged['key_points'], $slice['key_points']);
            $merged['entities'] = array_merge($merged['entities'], $slice['entities']);
            $merged['courses'] = array_merge($merged['courses'], $slice['courses']);
        }

        if (empty($merged['key_points'])) {
            $merged = $this->extractor->extract(
                $context['query'],
                $context['docs'],
                $context['relations'],
                'full corpus',
            );
        }

        $merged['key_points'] = array_values(array_unique($merged['key_points']));
        $merged['entities'] = array_values(array_unique($merged['entities']));
        $merged['entities'] = array_merge($merged['entities'], $context['entities']);

        $synth = $this->synthesizer->synthesize(
            $context['query'],
            $merged,
            $context['relations'],
            $context['docs'],
        );

        $graph = $this->graphBuilder->build(
            $merged['entities'],
            $context['relations'],
            40,
            80,
        );

        return [
            'title' => $synth['title'],
            'overview' => $synth['overview'],
            'knowledge_graph' => $graph,
            'learning_path' => ResultFormatter::normalizeLearningPath($synth['learning_path']),
            'evidence' => ResultFormatter::toEvidence($context['docs']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupDocsByTopic(array $docs): array
    {
        $groups = [];
        foreach ($docs as $doc) {
            $topicIds = $doc['topic_ids'] ?? ['default'];
            foreach ((array) $topicIds as $topicId) {
                $key = (string) $topicId;
                $groups[$key] ??= [];
                $groups[$key][] = $doc;
            }
        }

        if (count($groups) <= 1 && count($docs) > 0) {
            return ['all' => $docs];
        }

        return $this->limitTopicGroups($groups, 2);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $groups
     * @return array<string, list<array<string, mixed>>>
     */
    private function limitTopicGroups(array $groups, int $maxGroups): array
    {
        if (count($groups) <= $maxGroups) {
            return $groups;
        }

        uasort($groups, fn (array $a, array $b): int => count($b) <=> count($a));
        $kept = array_slice($groups, 0, $maxGroups, true);
        $merged = array_merge(...array_values(array_slice($groups, $maxGroups)));

        if (! empty($merged)) {
            $primaryKey = array_key_first($kept);
            $kept[$primaryKey] = array_merge($kept[$primaryKey] ?? [], $merged);
        }

        return $kept;
    }
}
