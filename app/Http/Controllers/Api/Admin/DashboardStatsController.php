<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchHistory;
use App\Support\Admin\AdminPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class DashboardStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $period = AdminPeriod::fromRequest($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $current = $this->metrics($period->from, $period->to);
        $previous = $this->metrics($period->prevFrom, $period->prevTo);

        return response()->json([
            'period' => [
                'from' => $period->from->toIso8601String(),
                'to' => $period->to->toIso8601String(),
            ],
            'total_searches' => [
                'value' => $current['total_searches'],
                'delta_pct' => $this->deltaPct($current['total_searches'], $previous['total_searches']),
            ],
            'active_users' => [
                'value' => $current['active_users'],
                'delta_pct' => $this->deltaPct($current['active_users'], $previous['active_users']),
            ],
            'avg_latency_ms' => [
                'value' => $current['avg_latency_ms'],
                'delta_pct' => $this->deltaPct($current['avg_latency_ms'], $previous['avg_latency_ms']),
            ],
            'zero_result_rate' => [
                'value' => $current['zero_result_rate'],
                'delta_pct' => $this->deltaPct($current['zero_result_rate'], $previous['zero_result_rate']),
            ],
        ]);
    }

    /**
     * @return array{total_searches: int, active_users: int, avg_latency_ms: float, zero_result_rate: float}
     */
    private function metrics(\Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $base = SearchHistory::query()
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $base)->count();
        $activeUsers = (clone $base)->distinct('user_id')->count('user_id');
        $avgLatency = (float) ((clone $base)->avg('total_latency_ms') ?? 0);
        $zeroResults = (clone $base)->where('status', 'zero_results')->count();
        $zeroRate = $total > 0 ? round(($zeroResults / $total) * 100, 1) : 0.0;

        return [
            'total_searches' => $total,
            'active_users' => $activeUsers,
            'avg_latency_ms' => round($avgLatency, 1),
            'zero_result_rate' => $zeroRate,
        ];
    }

    private function deltaPct(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
