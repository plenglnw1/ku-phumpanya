<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Models\SearchHistory;
use App\Services\GraphRag\GraphRagService;

final class ResolveSearchResult
{
    public function __construct(
        private readonly GraphRagService $graphRag,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(SearchHistory $searchHistory): array
    {
        return $searchHistory->result ?? $this->graphRag->search($searchHistory->query);
    }
}
