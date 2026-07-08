<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Search\CreateSearchHistory;
use App\Actions\Search\ResolveSearchResult;
use App\Http\Controllers\Controller;
use App\Http\Resources\SearchHistoryRecentResource;
use App\Http\Resources\SearchHistoryResource;
use App\Http\Resources\SearchHistoryShowResource;
use App\Models\SearchHistory;
use App\Services\GraphRag\GraphRagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SearchController extends Controller
{
    public function __construct(
        private readonly GraphRagService $graphRag,
        private readonly CreateSearchHistory $createSearchHistory,
        private readonly ResolveSearchResult $resolveSearchResult,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        $history = $this->createSearchHistory->execute(
            $request->user(),
            $validated['query'],
        );

        return SearchHistoryResource::make($history)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SearchHistory $searchHistory): SearchHistoryShowResource
    {
        abort_unless($searchHistory->user_id === $request->user()->id, 403);

        $tab = $request->query('tab', 'overview');
        if (! in_array($tab, ['overview', 'graph', 'learning'], true)) {
            $tab = 'overview';
        }

        $result = $this->resolveSearchResult->execute($searchHistory);

        return SearchHistoryShowResource::make([
            'id' => $searchHistory->id,
            'query' => $searchHistory->query,
            'tab' => $tab,
            'result' => $result,
            'created_at' => $searchHistory->created_at,
        ]);
    }

    public function recent(Request $request): AnonymousResourceCollection
    {
        $histories = SearchHistory::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        return SearchHistoryRecentResource::collection($histories);
    }

    public function suggestions(): JsonResponse
    {
        return response()->json([
            'suggestions' => $this->graphRag->suggestions(),
        ]);
    }
}
