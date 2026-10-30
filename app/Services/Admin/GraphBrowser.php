<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\SearchHistory;
use App\Services\GraphRag\DocumentSource;
use App\Services\GraphRag\ElasticClientFactory;
use App\Services\GraphRag\RelationGraphBuilder;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the admin knowledge-graph view from the same Elasticsearch data that search
 * uses, so no Cypher or ES query has to be typed.
 *
 * A graph is `{nodes: list<{id, label, type, source?, url?}>, edges: list<{from, to, label}>}`.
 */
final class GraphBrowser
{
    private const RELATION_LIMIT = 200;

    private const DOCUMENT_NODES = 15;

    private const PANEL_DOCUMENTS = 10;

    public function __construct(
        private readonly ElasticClientFactory $factory,
        private readonly RelationGraphBuilder $builder,
    ) {}

    /**
     * Relations of one topic and/or predicate, plus that topic's documents as nodes.
     *
     * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>, note: ?string}
     */
    public function browse(?string $topicId, ?string $source, ?string $predicate): array
    {
        $key = 'admin:kg:browse:'.md5(json_encode([$topicId, $source, $predicate]));

        return Cache::remember($key, 300, fn (): array => $this->buildBrowse($topicId, $source, $predicate));
    }

    /**
     * The graph and evidence exactly as stored for one search.
     *
     * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>, note: ?string, evidence: list<array<string, string>>}
     */
    public function replay(SearchHistory $history): array
    {
        $result = is_array($history->result) ? $history->result : [];
        $graph = $result['knowledge_graph'] ?? [];

        $nodes = collect($graph['nodes'] ?? [])
            ->map(fn (array $n): array => [
                'id' => (string) ($n['label'] ?? ''),
                'label' => (string) ($n['label'] ?? ''),
                'type' => (string) ($n['type'] ?? 'topic'),
            ])
            ->filter(fn (array $n): bool => $n['id'] !== '')
            ->unique('id')
            ->values()
            ->all();

        $edges = collect($graph['edges'] ?? [])
            ->map(fn (array $e): array => [
                'from' => (string) ($e['from'] ?? ''),
                'to' => (string) ($e['to'] ?? ''),
                'label' => $this->predicateLabel((string) ($e['type'] ?? 'relatedTo')),
            ])
            ->filter(fn (array $e): bool => $e['from'] !== '' && $e['to'] !== '')
            ->values()
            ->all();

        $evidence = collect($result['evidence'] ?? [])
            ->map(fn (array $item): array => [
                'title' => (string) ($item['title'] ?? ''),
                'source' => (string) ($item['source'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
            ])
            ->values()
            ->all();

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'note' => $nodes === [] ? 'การค้นหานี้ไม่มีกราฟที่บันทึกไว้' : null,
            'evidence' => $evidence,
        ];
    }

    /**
     * Documents that mention a node, for the side panel.
     *
     * @return list<array{title: string, source: string, url: string, year: string}>
     */
    public function documentsFor(string $entity, ?string $source = null): array
    {
        $client = $this->factory->make();
        if (! $client instanceof Client || trim($entity) === '') {
            return [];
        }

        $filter = $source ? [['term' => ['source' => $source]]] : [];

        try {
            $hits = $client->search([
                'index' => config('elasticsearch.indices.docs'),
                'body' => [
                    'size' => self::PANEL_DOCUMENTS,
                    '_source' => ['title', 'source', 'url', 'year'],
                    'query' => ['bool' => [
                        'must' => [['multi_match' => [
                            'query' => str_replace('_', ' ', $entity),
                            'fields' => DocumentSource::SEARCH_FIELDS,
                        ]]],
                        'filter' => $filter,
                    ]],
                ],
            ])->asArray()['hits']['hits'] ?? [];
        } catch (Throwable) {
            return [];
        }

        return array_map(fn (array $hit): array => $this->document($hit['_source'] ?? []), $hits);
    }

    public function predicateLabel(string $predicate): string
    {
        return (string) (config("knowledge_graph.predicate_labels.{$predicate}") ?? $predicate);
    }

    /**
     * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>, note: ?string}
     */
    private function buildBrowse(?string $topicId, ?string $source, ?string $predicate): array
    {
        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return ['nodes' => [], 'edges' => [], 'note' => 'Elasticsearch ไม่ได้เปิดใช้งานบนเซิร์ฟเวอร์นี้'];
        }

        try {
            $relations = $this->relations($client, $topicId, $predicate);
            $documents = $topicId !== null || $source !== null
                ? $this->topicDocuments($client, $topicId, $source)
                : [];
        } catch (Throwable $e) {
            return ['nodes' => [], 'edges' => [], 'note' => 'อ่านข้อมูลจาก Elasticsearch ไม่ได้: '.Str::limit($e->getMessage(), 120)];
        }

        $nodes = [];
        $edges = [];

        if ($relations !== []) {
            $entities = array_values(array_unique(array_merge(
                array_column($relations, 'subject'),
                array_column($relations, 'object'),
            )));
            $graph = $this->builder->build($entities, $relations, self::RELATION_LIMIT, self::RELATION_LIMIT);

            foreach ($graph['nodes'] as $node) {
                $nodes[$node['label']] = ['id' => $node['label'], 'label' => $node['label'], 'type' => $node['type']];
            }
            foreach ($graph['edges'] as $edge) {
                $edges[] = ['from' => $edge['from'], 'to' => $edge['to'], 'label' => $this->predicateLabel($edge['type'])];
            }
        }

        if ($documents !== []) {
            $hub = $topicId !== null
                ? (string) config("knowledge_graph.topics.{$topicId}", "หัวข้อ {$topicId}")
                : (string) config("knowledge_graph.source_labels.{$source}", (string) $source);
            $nodes[$hub] = ['id' => $hub, 'label' => $hub, 'type' => 'topic'];

            foreach ($documents as $doc) {
                $id = 'doc:'.$doc['url'];
                $nodes[$id] = [
                    'id' => $id,
                    'label' => Str::limit($doc['title'], 40),
                    'type' => 'document',
                    'source' => $doc['source'],
                    'url' => $doc['url'],
                    'title' => $doc['title'],
                ];
                $edges[] = ['from' => $id, 'to' => $hub, 'label' => $this->predicateLabel('inTopic')];
            }
        }

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'note' => $nodes === [] ? 'ไม่พบความสัมพันธ์หรือเอกสารตามตัวกรองนี้' : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relations(Client $client, ?string $topicId, ?string $predicate): array
    {
        $filter = [];
        if ($topicId !== null) {
            $filter[] = ['term' => ['topic_id' => $topicId]];
        }
        if ($predicate !== null) {
            $filter[] = ['term' => ['predicate' => $predicate]];
        }

        $hits = $client->search([
            'index' => config('elasticsearch.indices.relations'),
            'body' => [
                'size' => self::RELATION_LIMIT,
                'query' => $filter === [] ? ['match_all' => new \stdClass] : ['bool' => ['filter' => $filter]],
            ],
        ])->asArray()['hits']['hits'] ?? [];

        return array_values(array_filter(
            array_map(static fn (array $hit): array => $hit['_source'] ?? [], $hits),
            static fn (array $r): bool => ($r['subject'] ?? '') !== '' && ($r['object'] ?? '') !== '',
        ));
    }

    /**
     * @return list<array{title: string, source: string, url: string, year: string}>
     */
    private function topicDocuments(Client $client, ?string $topicId, ?string $source): array
    {
        $filter = [];
        if ($topicId !== null) {
            $filter[] = ['term' => ['topic_ids' => $topicId]];
        }
        if ($source !== null) {
            $filter[] = ['term' => ['source' => $source]];
        }

        $hits = $client->search([
            'index' => config('elasticsearch.indices.docs'),
            'body' => [
                'size' => self::DOCUMENT_NODES,
                '_source' => ['title', 'source', 'url', 'year'],
                'query' => ['bool' => ['filter' => $filter]],
                'sort' => [['year' => ['order' => 'desc', 'missing' => '_last', 'unmapped_type' => 'integer']]],
            ],
        ])->asArray()['hits']['hits'] ?? [];

        return array_map(fn (array $hit): array => $this->document($hit['_source'] ?? []), $hits);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{title: string, source: string, url: string, year: string}
     */
    private function document(array $source): array
    {
        return [
            'title' => (string) ($source['title'] ?? 'Untitled'),
            'source' => (string) ($source['source'] ?? ''),
            'url' => (string) ($source['url'] ?? ''),
            'year' => (string) ($source['year'] ?? ''),
        ];
    }
}
