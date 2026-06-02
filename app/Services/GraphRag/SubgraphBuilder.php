<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Collection;

final class SubgraphBuilder
{
    public function __construct(
        private readonly ElasticClientFactory $factory,
    ) {}

    /**
     * @param  list<string>  $entities
     * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>, center: array<string, string>, description: string}
     */
    public function build(array $entities, int $maxNodes = 40, int $maxEdges = 80): array
    {
        $entities = array_values(array_unique(array_filter($entities)));
        if (count($entities) === 0) {
            return $this->emptyGraph();
        }

        $links = $this->fetchLinks($entities);
        $edges = collect($links)->take($maxEdges)->map(function (array $link): array {
            return [
                'from' => (string) $link['left_entity'],
                'to' => (string) $link['right_entity'],
                'type' => (string) $link['relation'],
            ];
        })->all();

        $nodeNames = collect($edges)
            ->flatMap(fn (array $edge): array => [$edge['from'], $edge['to']])
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
            'description' => 'GraphRAG subgraph extracted from KU Forest + KUKR + KU MOOC relations.',
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @param  list<string>  $entities
     * @return list<array<string, mixed>>
     */
    private function fetchLinks(array $entities): array
    {
        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return $this->fallbackLinks($entities);
        }

        try {
            $response = $client->search([
                'index' => config('elasticsearch.indices.entity_links'),
                'body' => [
                    'size' => 200,
                    'query' => [
                        'bool' => [
                            'should' => [
                                ['terms' => ['left_entity' => $entities]],
                                ['terms' => ['right_entity' => $entities]],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                    'sort' => [['weight' => ['order' => 'desc']]],
                ],
            ])->asArray();

            return array_map(
                static fn (array $hit): array => $hit['_source'] ?? [],
                $response['hits']['hits'] ?? [],
            );
        } catch (\Throwable) {
            return $this->fallbackLinks($entities);
        }
    }

    /**
     * @param  list<string>  $entities
     * @return list<array<string, mixed>>
     */
    private function fallbackLinks(array $entities): array
    {
        $topics = config('graphrag_seed.topics', []);

        return collect($topics)
            ->flatMap(function (array $topic): Collection {
                $title = (string) ($topic['title'] ?? 'Topic');
                $links = collect();

                foreach (($topic['faculty_tags'] ?? []) as $faculty) {
                    $links->push([
                        'left_entity' => $title,
                        'right_entity' => (string) $faculty,
                        'relation' => 'linkedFaculty',
                        'weight' => 1,
                    ]);
                }
                foreach (($topic['bcg_tags'] ?? []) as $tag) {
                    $links->push([
                        'left_entity' => $title,
                        'right_entity' => 'BCG_'.$tag,
                        'relation' => 'relatedTo',
                        'weight' => 1,
                    ]);
                }
                foreach (($topic['courses'] ?? []) as $course) {
                    $links->push([
                        'left_entity' => $title,
                        'right_entity' => (string) ($course['course_id'] ?? 'course'),
                        'relation' => 'hasCourse',
                        'weight' => 1,
                    ]);
                }

                return $links;
            })
            ->filter(fn (array $link): bool => in_array($link['left_entity'], $entities, true) || in_array($link['right_entity'], $entities, true))
            ->values()
            ->all();
    }

    /**
     * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>, center: array<string, string>, description: string}
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

        if (in_array($entity, ['เกษตร', 'อุตสาหกรรมเกษตร', 'วนศาสตร์'], true)) {
            return 'faculty';
        }

        if (in_array($entity, ['KU_Forest', 'KUKR', 'KU_MOOC'], true)) {
            return 'source';
        }

        return 'topic';
    }
}

