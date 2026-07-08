<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SearchHistory
 */
final class SearchHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'query' => $this->query,
            'result' => $this->result,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
