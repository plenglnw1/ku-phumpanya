<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Services\GraphRag\GraphRagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(
        private readonly GraphRagService $graphRag,
    ) {}

    public function index(Request $request): View
    {
        return view('search.index', [
            'suggestions' => $this->graphRag->suggestions(),
            'recentSearches' => $this->recentSearches($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        $topic = $this->graphRag->search($validated['query']);

        $history = SearchHistory::query()->create([
            'user_id' => $request->user()->id,
            'query' => $validated['query'],
            'result' => $topic,
        ]);

        return redirect()->route('search.show', $history);
    }

    public function show(Request $request, SearchHistory $searchHistory): View
    {
        abort_unless($searchHistory->user_id === $request->user()->id, 403);

        $topic = $searchHistory->result ?? $this->graphRag->search($searchHistory->query);
        $tab = $request->query('tab', 'overview');

        if (! in_array($tab, ['overview', 'graph', 'learning'], true)) {
            $tab = 'overview';
        }

        return view('search.show', [
            'searchHistory' => $searchHistory,
            'topic' => $topic,
            'evidence' => $topic['evidence'] ?? [],
            'tab' => $tab,
            'recentSearches' => $this->recentSearches($request),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SearchHistory>
     */
    private function recentSearches(Request $request)
    {
        return SearchHistory::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();
    }
}
