<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PhumpanyaMockCatalog;
use Illuminate\Http\JsonResponse;

final class SmartPicksController extends Controller
{
    public function __construct(
        private readonly PhumpanyaMockCatalog $catalog,
    ) {}

    public function index(): JsonResponse
    {
        $data = $this->catalog->smartPicks();
        $picks = $data['picks'] ?? [];

        $items = collect($picks)->map(function (array $pick): array {
            $tags = $pick['tags'] ?? [];

            return [
                'title' => (string) ($pick['title'] ?? ''),
                'description' => (string) ($pick['meta'] ?? implode(' · ', $tags)),
                'query' => (string) ($pick['title'] ?? ''),
                'badge' => ! empty($tags) ? (string) $tags[0] : (($pick['featured'] ?? false) ? 'Featured' : ''),
            ];
        })->values()->all();

        return response()->json([
            'smart_picks' => [
                'headline' => 'AI Recommendations',
                'items' => $items,
            ],
        ]);
    }
}
