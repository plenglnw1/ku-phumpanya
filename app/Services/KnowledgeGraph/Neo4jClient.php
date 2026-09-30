<?php

declare(strict_types=1);

namespace App\Services\KnowledgeGraph;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only access to the BCG knowledge graph through the Neo4j HTTP transaction API.
 */
final class Neo4jClient
{
    public function isConfigured(): bool
    {
        return (bool) config('knowledge_graph.enabled')
            && (string) config('knowledge_graph.password') !== '';
    }

    /**
     * Run one read query and return its rows plus the nodes and relationships it touched.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{columns: list<string>, rows: list<list<mixed>>, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, truncated: bool}
     *
     * @throws KnowledgeGraphException
     */
    public function read(string $cypher, array $parameters = []): array
    {
        if (! $this->isConfigured()) {
            throw new KnowledgeGraphException('The knowledge graph is not configured.');
        }

        $url = rtrim((string) config('knowledge_graph.http_url'), '/');
        $database = rawurlencode((string) config('knowledge_graph.database'));

        try {
            $response = Http::withBasicAuth(
                (string) config('knowledge_graph.username'),
                (string) config('knowledge_graph.password'),
            )
                ->withHeaders(['Access-Mode' => 'READ'])
                ->acceptJson()
                ->timeout((int) config('knowledge_graph.timeout'))
                ->post("{$url}/db/{$database}/tx/commit", [
                    'statements' => [[
                        'statement' => $cypher,
                        'parameters' => (object) $parameters,
                        'resultDataContents' => ['row', 'graph'],
                    ]],
                ]);
        } catch (ConnectionException) {
            throw new KnowledgeGraphException('The knowledge graph database is not reachable.');
        }

        if (! $response->successful()) {
            throw new KnowledgeGraphException("The knowledge graph database answered HTTP {$response->status()}.");
        }

        $error = $response->json('errors.0');
        if (is_array($error)) {
            throw new KnowledgeGraphException((string) ($error['message'] ?? 'Query failed.'));
        }

        return $this->normalize((array) $response->json('results.0', []));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{columns: list<string>, rows: list<list<mixed>>, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, truncated: bool}
     */
    private function normalize(array $result): array
    {
        $rowLimit = (int) config('knowledge_graph.row_limit');
        $nodeLimit = (int) config('knowledge_graph.node_limit');
        $data = (array) ($result['data'] ?? []);

        $rows = [];
        $nodes = [];
        $edges = [];
        foreach ($data as $record) {
            if (count($rows) < $rowLimit) {
                $rows[] = array_values((array) ($record['row'] ?? []));
            }
            foreach ((array) ($record['graph']['nodes'] ?? []) as $node) {
                $id = (string) ($node['elementId'] ?? $node['id']);
                if (count($nodes) < $nodeLimit || isset($nodes[$id])) {
                    $nodes[$id] = $this->node($id, $node);
                }
            }
            foreach ((array) ($record['graph']['relationships'] ?? []) as $edge) {
                $id = (string) ($edge['elementId'] ?? $edge['id']);
                $edges[$id] = [
                    'id' => $id,
                    'from' => (string) ($edge['startNodeElementId'] ?? $edge['startNode']),
                    'to' => (string) ($edge['endNodeElementId'] ?? $edge['endNode']),
                    'type' => (string) $edge['type'],
                ];
            }
        }

        $edges = array_filter($edges, static fn (array $e): bool => isset($nodes[$e['from']], $nodes[$e['to']]));

        return [
            'columns' => array_values((array) ($result['columns'] ?? [])),
            'rows' => $rows,
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
            'truncated' => count($data) > $rowLimit || count($nodes) >= $nodeLimit,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(string $id, array $node): array
    {
        $properties = (array) ($node['properties'] ?? []);
        $labels = array_values((array) ($node['labels'] ?? []));

        return [
            'id' => $id,
            'label' => $labels[0] ?? 'Node',
            'name' => (string) ($properties['name'] ?? $properties['title'] ?? $properties['name_en']
                ?? $properties['name_th'] ?? $properties['code'] ?? $id),
            'properties' => $properties,
        ];
    }
}
