<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Services\PhumpanyaMockCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmartPicksController extends Controller
{
    public function __construct(
        private readonly PhumpanyaMockCatalog $catalog,
    ) {}

    public function index(Request $request): View
    {
        return view('smart-picks.index', [
            'smartPicks' => $this->catalog->smartPicks(),
            'recentSearches' => SearchHistory::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
