<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchHistory;
use App\Support\Admin\AdminPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $period = AdminPeriod::fromRequest($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $base = SearchHistory::query()
            ->whereBetween('created_at', [$period->from, $period->to]);

        $searchVolumeTrend = (clone $base)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->date,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $topTopics = (clone $base)
            ->selectRaw('query, COUNT(*) as count')
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'query' => (string) $row->query,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $roleBreakdown = (clone $base)
            ->selectRaw('COALESCE(role_snapshot, \'unknown\') as role, COUNT(*) as count')
            ->groupBy('role')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'role' => (string) $row->role,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $topNodes = $this->aggregateTopNodes($period);

        return response()->json([
            'period' => [
                'from' => $period->from->toIso8601String(),
                'to' => $period->to->toIso8601String(),
            ],
            'search_volume_trend' => $searchVolumeTrend,
            'top_topics' => $topTopics,
            'role_breakdown' => $roleBreakdown,
            'top_nodes' => $topNodes,
        ]);
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function aggregateTopNodes(AdminPeriod $period): array
    {
        $rows = SearchHistory::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->whereNotNull('metrics')
            ->pluck('metrics');

        $counts = [];
        foreach ($rows as $metrics) {
            $labels = is_array($metrics) ? ($metrics['top_node_labels'] ?? []) : [];
            foreach ($labels as $label) {
                $key = (string) $label;
                if ($key === '') {
                    continue;
                }
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        arsort($counts);

        return collect($counts)
            ->take(10)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();
    }
}
