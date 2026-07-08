<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Search\ResolveSearchResult;
use App\Http\Controllers\Controller;
use App\Models\SearchHistory;
use App\Services\GraphRag\GraphRagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LearningController extends Controller
{
    public function __construct(
        private readonly GraphRagService $graphRag,
        private readonly ResolveSearchResult $resolveSearchResult,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $searchHistoryId = $request->query('search_history_id');

        if ($searchHistoryId !== null) {
            $searchHistory = SearchHistory::query()->findOrFail($searchHistoryId);
            abort_unless($searchHistory->user_id === $request->user()->id, 403);

            return response()->json([
                'search_history_id' => $searchHistory->id,
                'result' => $this->resolveSearchResult->execute($searchHistory),
            ]);
        }

        return response()->json([
            'search_history_id' => null,
            'result' => $this->graphRag->search('carbon footprint in agriculture'),
        ]);
    }
}
