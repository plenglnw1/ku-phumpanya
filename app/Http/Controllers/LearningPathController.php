<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Services\GraphRag\GraphRagService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningPathController extends Controller
{
    public function __construct(
        private readonly GraphRagService $graphRag,
    ) {}

    public function show(Request $request, ?SearchHistory $searchHistory = null): View
    {
        if ($searchHistory !== null) {
            abort_unless($searchHistory->user_id === $request->user()->id, 403);
            $topic = $this->graphRag->search($searchHistory->query);
        } else {
            $topic = $this->graphRag->search('carbon footprint in agriculture');
        }

        return view('learning.show', [
            'searchHistory' => $searchHistory,
            'topic' => $topic,
            'recentSearches' => SearchHistory::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
