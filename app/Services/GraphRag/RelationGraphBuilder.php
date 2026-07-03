<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Collection;

/**
 * Build knowledge graph from KU-BCG relation triples (subject → predicate → object).
 */
final class RelationGraphBuilder
{
    public function __construct(
        private readonly ElasticClientFactory $factory,
    ) {}

    /**
     * @param  list<string>  $entities
     * @param  list<array<string, mixed>>  $prefetchedRelations
     * @return array{center: array<string, string>, description: string, nodes: list<array<string, string>>, edges: list<array<string, string>>}
     */
    public function build(array $entities, array $prefetchedRelations = [], int $maxNodes = 40, int $maxEdges = 80): array
    {
        $entities = array_values(array_unique(array_filter($entities)));
        if (count($entities) === 0) {
            return $this->emptyGraph();
        }

        $links = ! empty($prefetchedRelations)
            ? $prefetchedRelations
            : $this->fetchRelations($entities);

        $edges = collect($links)->take($maxEdges)->map(fn (array $link): array => [
            'from' => (string) ($link['subject'] ?? $link['left_entity'] ?? ''),
            'to' => (string) ($link['object'] ?? $link['right_entity'] ?? ''),
            'type' => (string) ($link['predicate'] ?? $link['relation'] ?? 'relatedTo'),
        ])->filter(fn (array $edge): bool => $edge['from'] !== '' && $edge['to'] !== '')->values()->all();

        $nodeNames = collect($edges)
            ->flatMap(fn (array $edge): array => [$edge['from'], $edge['to']])
            ->merge($entities)
            ->unique()
            ->take($maxNodes)
            ->values();

        $palette = ['#EAB308', '#F472B6', '#A855F7', '#3B82F6', '#22C55E', '#86EFAC'];
        $nodes = $nodeNames->map(fn (string $name, int $index): array => [
            'label' => $name,
            'color' => $palette[$index % count($palette)],
            'type' => $this->inferNodeType($name),
        ])->all();

        return [
            'center' => ['label' => $entities[0], 'color' => '#2D5A43', 'type' => 'topic'],
            'description' => 'GraphRAG subgraph from KU BCG relations (KUKR + KU Forest + KU MOOC).',
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @param  list<string>  $entities
     * @return list<array<string, mixed>>
     */
    private function fetchRelations(array $entities): array
    {
        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return $this->fallbackRelations($entities);
        }

        try {
            $response = $client->search([
                'index' => config('elasticsearch.indices.relations'),
                'body' => [
                    'size' => 100,
                    'query' => [
                        'bool' => [
                            'should' => [
                                ['terms' => ['subject.keyword' => $entities]],
                                ['terms' => ['object.keyword' => $entities]],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ])->asArray();

            return array_map(
                static fn (array $hit): array => $hit['_source'] ?? [],
                $response['hits']['hits'] ?? [],
            );
        } catch (\Throwable) {
            return $this->fallbackRelations($entities);
        }
    }

    /**
     * @param  list<string>  $entities
     * @return list<array<string, mixed>>
     */
    private function fallbackRelations(array $entities): array
    {
        return collect(config('graphrag_seed.topics', []))
            ->flatMap(function (array $topic): Collection {
                $links = collect();
                foreach (($topic['faculty_tags'] ?? []) as $faculty) {
                    $links->push([
                        'subject' => $topic['title'] ?? 'Topic',
                        'predicate' => 'linkedFaculty',
                        'object' => $faculty,
                    ]);
                }
                foreach (($topic['bcg_tags'] ?? []) as $tag) {
                    $links->push([
                        'subject' => $topic['title'] ?? 'Topic',
                        'predicate' => 'relatedTo',
                        'object' => 'BCG_'.$tag,
                    ]);
                }

                return $links;
            })
            ->filter(fn (array $link): bool => in_array($link['subject'], $entities, true)
                || in_array($link['object'], $entities, true))
            ->values()
            ->all();
    }

    /**
     * @return array{center: array<string, string>, description: string, nodes: list<array<string, string>>, edges: list<array<string, string>>}
     */
    private function emptyGraph(): array
    {
        return [
            'center' => ['label' => 'Knowledge Graph', 'color' => '#2D5A43', 'type' => 'topic'],
            'description' => 'No graph relations found for this query.',
            'nodes' => [],
            'edges' => [],
        ];
    }

    private function inferNodeType(string $entity): string
    {
        $normalized = mb_strtolower($entity);

        if (str_starts_with($normalized, 'bcg_')) {
            return 'bcg_pillar';
        }

        if (str_starts_with($normalized, 'kumooc')) {
            return 'course';
        }

        if (in_array($entity, ['เกษตร', 'อุตสาหกรรมเกษตร', 'วนศาสตร์', 'ประมง'], true)) {
            return 'faculty';
        }

        return 'topic';
    }
}
