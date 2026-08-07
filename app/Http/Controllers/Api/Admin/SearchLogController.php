<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchHistory;
use App\Support\Admin\AdminPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SearchLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $period = AdminPeriod::fromRequest($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $logs = $this->filteredQuery($request, $period)
            ->with(['user:id,name,email,role'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($logs);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $period = AdminPeriod::fromRequest($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $filename = 'search-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request, $period): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'timestamp',
                'user_role',
                'faculty',
                'raw_query',
                'query_type',
                'status',
                'total_nodes_found',
                'latency_ms',
                'top_recommended_nodes',
            ]);

            $this->filteredQuery($request, $period)
                ->orderByDesc('created_at')
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        /** @var SearchHistory $row */
                        $labels = is_array($row->metrics)
                            ? ($row->metrics['top_node_labels'] ?? [])
                            : [];

                        fputcsv($handle, [
                            $row->created_at?->format('Y-m-d H:i'),
                            $row->role_snapshot ?? '',
                            $row->faculty_snapshot ?? '',
                            $row->query,
                            $row->query_type ?? '',
                            $row->status,
                            $row->total_nodes_found ?? 0,
                            $row->total_latency_ms ?? '',
                            empty($labels) ? '-' : implode(', ', $labels),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return Builder<SearchHistory>
     */
    private function filteredQuery(Request $request, AdminPeriod $period): Builder
    {
        $query = SearchHistory::query()
            ->whereBetween('created_at', [$period->from, $period->to]);

        if ($request->filled('role') && $request->query('role') !== 'all') {
            $query->where('role_snapshot', (string) $request->query('role'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = (string) $request->query('q');
            $query->where('query', 'like', '%'.$q.'%');
        }

        return $query;
    }
}
