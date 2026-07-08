<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string, mixed> $resource
 */
final class SearchHistoryShowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'id' => $data['id'],
            'query' => $data['query'],
            'tab' => $data['tab'],
            'result' => $data['result'],
            'created_at' => $data['created_at'],
        ];
    }
}
