<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Recommendations\SmartPicksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SmartPicksController extends Controller
{
    public function __construct(
        private readonly SmartPicksService $smartPicks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Envelope kept as documented in docs/api-spec.md; `items` gained the fields
        // the recommender produces (reasons, match, keywords) additively.
        return response()->json([
            'smart_picks' => $this->smartPicks->forUser($request->user()),
        ]);
    }
}
