<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Models\SearchHistory;
use App\Models\User;
use App\Services\GraphRag\GraphRagService;

final class CreateSearchHistory
{
    public function __construct(
        private readonly GraphRagService $graphRag,
    ) {}

    public function execute(User $user, string $query): SearchHistory
    {
        $result = $this->graphRag->search($query);

        return SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => $query,
            'result' => $result,
        ]);
    }
}
