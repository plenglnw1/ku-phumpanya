<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Models\SearchHistory;
use App\Models\User;
use App\Services\GraphRag\GraphRagService;
use Throwable;

final class CreateSearchHistory
{
    public function __construct(
        private readonly GraphRagService $graphRag,
    ) {}

    public function execute(User $user, string $query): SearchHistory
    {
        $start = microtime(true);
        $status = 'success';
        $result = null;
        $caught = null;

        try {
            $result = $this->graphRag->search($query);
        } catch (Throwable $e) {
            $status = 'error';
            $caught = $e;
        }

        $totalLatencyMs = (int) round((microtime(true) - $start) * 1000);

        $nodes = is_array($result) ? ($result['knowledge_graph']['nodes'] ?? []) : [];
        $evidence = is_array($result) ? ($result['evidence'] ?? []) : [];

        if ($status === 'success' && count($nodes) === 0 && empty($evidence)) {
            $status = 'zero_results';
        }

        $timing = is_array($result) ? ($result['_meta']['timing'] ?? []) : [];
        $meta = is_array($result) ? ($result['_meta'] ?? []) : [];

        $nodesBreakdown = [];
        foreach ($nodes as $node) {
            $type = (string) ($node['type'] ?? 'unknown');
            $nodesBreakdown[$type] = ($nodesBreakdown[$type] ?? 0) + 1;
        }

        $topNodeLabels = collect($nodes)
            ->pluck('label')
            ->filter()
            ->take(5)
            ->values()
            ->all();

        $tier = is_array($result) ? ($result['tier'] ?? null) : null;
        $queryType = match ($tier) {
            'advanced' => 'complex',
            'intermediate', 'basic' => 'simple',
            default => null,
        };

        $history = SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => $query,
            'result' => $result,
            'status' => $status,
            'query_type' => $queryType,
            'role_snapshot' => $user->role?->value,
            'faculty_snapshot' => $user->faculty,
            'total_latency_ms' => $totalLatencyMs,
            'retrieval_latency_ms' => isset($timing['retrieval_ms']) ? (int) $timing['retrieval_ms'] : null,
            'synthesis_latency_ms' => isset($timing['synthesis_ms']) ? (int) $timing['synthesis_ms'] : null,
            'total_nodes_found' => count($nodes),
            'metrics' => [
                'decomposed_queries' => $meta['sub_queries'] ?? [],
                'nodes_breakdown' => $nodesBreakdown,
                'top_node_labels' => $topNodeLabels,
                'docs_retrieved' => $meta['docs_retrieved'] ?? null,
                'relations_retrieved' => $meta['relations_retrieved'] ?? null,
                'error_message' => $caught?->getMessage(),
            ],
        ]);

        if ($caught !== null) {
            throw $caught;
        }

        return $history;
    }
}
