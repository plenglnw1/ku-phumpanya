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
     * @return array{center: array<string, string>, description: string, nodes: list<array<string, string>>, edges: list<array<string, mixed>>}
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

        $candidates = collect($links)
            ->map(fn (array $link): array => $this->toEdge($link))
            ->filter(fn (array $edge): bool => $edge['from'] !== '' && $edge['to'] !== '')
            ->unique(fn (array $edge): string => $edge['from']."\0".$edge['type']."\0".$edge['to'])
            ->values()
            ->all();

        $edges = self::selectEdges($candidates, $entities, $maxNodes, $maxEdges);

        $edgeNodes = collect($edges)->flatMap(fn (array $edge): array => [$edge['from'], $edge['to']])->unique();
        $nodeNames = $edgeNodes
            ->merge($entities)
            ->unique()
            ->take(max($maxNodes, $edgeNodes->count()))
            ->values();

        // Non-Degree course titles carry no naming convention, so the relation marks them.
        $courseNames = collect($edges)
            ->where('type', 'isCourseFor')
            ->pluck('from')
            ->flip();

        $palette = ['#EAB308', '#F472B6', '#A855F7', '#3B82F6', '#22C55E', '#86EFAC'];
        $nodes = $nodeNames->map(fn (string $name, int $index): array => [
            'label' => $name,
            'color' => $palette[$index % count($palette)],
            'type' => $courseNames->has($name) ? 'course' : $this->inferNodeType($name),
        ])->all();

        return [
            'center' => ['label' => $entities[0], 'color' => '#2D5A43', 'type' => 'topic'],
            'description' => 'กราฟความรู้จากความสัมพันธ์ในข้อมูล KU BCG (งานวิจัย KUKR, KU Forest, คอร์ส KU MOOC และชุดวิชา Non-Degree)',
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Picks at most `$maxEdges` edges touching at most `$maxNodes` nodes.
     *
     * Curated edges (no `origin`) are always kept. The rest are taken by priority —
     * derived by `weight`, then AI-suggested by `confidence` — but only when they share
     * a node with the graph built so far (seeded with the query entities), so the trim
     * removes the weakest edges at the fringe instead of splitting the graph.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<string>  $entities
     * @return list<array<string, mixed>>
     */
    public static function selectEdges(array $candidates, array $entities, int $maxNodes, int $maxEdges): array
    {
        $ranked = collect($candidates)
            ->map(fn (array $edge, int $i): array => [$edge, self::priority($edge), $i])
            ->sort(fn (array $a, array $b): int => [$a[1][0], -$a[1][1], $a[2]] <=> [$b[1][0], -$b[1][1], $b[2]])
            ->pluck(0)
            ->values();

        $selected = $ranked->filter(fn (array $edge): bool => ! isset($edge['origin']))->take($maxEdges)->values()->all();
        $nodes = [];
        foreach ($selected as $edge) {
            $nodes[$edge['from']] = true;
            $nodes[$edge['to']] = true;
        }
        $anchors = $nodes + array_fill_keys($entities, true);

        $pending = $ranked->filter(fn (array $edge): bool => isset($edge['origin']))->values()->all();
        while (count($selected) < $maxEdges && $pending !== []) {
            $picked = null;
            foreach ($pending as $i => $edge) {
                $joins = isset($anchors[$edge['from']]) || isset($anchors[$edge['to']]);
                $newNodes = (isset($nodes[$edge['from']]) ? 0 : 1) + (isset($nodes[$edge['to']]) || $edge['from'] === $edge['to'] ? 0 : 1);
                if ($joins && count($nodes) + $newNodes <= $maxNodes) {
                    $picked = $i;
                    break;
                }
            }
            if ($picked === null) {
                break;
            }

            $edge = $pending[$picked];
            unset($pending[$picked]);
            $selected[] = $edge;
            $nodes[$edge['from']] = true;
            $nodes[$edge['to']] = true;
            $anchors[$edge['from']] = true;
            $anchors[$edge['to']] = true;
        }

        return array_values($selected);
    }

    /**
     * @return array{0: int, 1: float} tier (curated 0, derived 1, AI 2, other 3) and strength
     */
    private static function priority(array $edge): array
    {
        return match ($edge['origin'] ?? null) {
            null => [0, 0.0],
            'derived' => [1, (float) ($edge['weight'] ?? 0)],
            'ai_suggested' => [2, (float) ($edge['confidence'] ?? 0)],
            default => [3, 0.0],
        };
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function toEdge(array $link): array
    {
        $edge = [
            'from' => (string) ($link['subject'] ?? $link['left_entity'] ?? ''),
            'to' => (string) ($link['object'] ?? $link['right_entity'] ?? ''),
            'type' => (string) ($link['predicate'] ?? $link['relation'] ?? 'relatedTo'),
        ];

        if (is_string($link['origin'] ?? null) && $link['origin'] !== '') {
            $edge['origin'] = $link['origin'];
        }
        foreach (['weight', 'confidence'] as $key) {
            if (is_numeric($link[$key] ?? null)) {
                $edge[$key] = (float) $link[$key];
            }
        }
        if (is_string($link['reason_th'] ?? null) && trim($link['reason_th']) !== '') {
            $edge['reason_th'] = trim($link['reason_th']);
        }

        return $edge;
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
            'description' => 'ไม่พบความสัมพันธ์ในกราฟความรู้สำหรับคำค้นนี้',
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
