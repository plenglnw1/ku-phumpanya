<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class QdrantRetriever
{
    /**
     * Semantic vector search via Qdrant.
     *
     * 1. Embed query via embed_server.py  (QDRANT_EMBEDDING_URL)
     * 2. POST /collections/{name}/points/search to Qdrant
     *
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $query, int $size = 6): array
    {
        return $this->searchCollection(
            (string) config('qdrant.collections.docs'),
            $query,
            $size,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function retrieveRelations(string $query, int $size = 10): array
    {
        return $this->searchCollection(
            (string) config('qdrant.collections.relations'),
            $query,
            $size,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCollection(string $collection, string $query, int $size): array
    {
        $vector = $this->embed($query);
        if (empty($vector)) {
            return [];
        }

        $host = rtrim((string) config('qdrant.host'), '/');
        $apiKey = (string) config('qdrant.api_key');
        $headers = $apiKey !== '' ? ['api-key' => $apiKey] : [];

        try {
            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->post("{$host}/collections/{$collection}/points/search", [
                    'vector' => ['name' => $collection, 'vector' => $vector],
                    'limit' => $size,
                    'with_payload' => true,
                ]);
        } catch (\Throwable $e) {
            Log::warning('QdrantRetriever: search failed', [
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('QdrantRetriever: non-200 response', [
                'collection' => $collection,
                'status' => $response->status(),
            ]);

            return [];
        }

        return array_map(
            static fn (array $hit): array => array_merge(
                ['_score' => $hit['score'] ?? 0.0],
                $hit['payload'] ?? [],
            ),
            $response->json('result', []),
        );
    }

    /**
     * Call embed_server.py to get a 384-dim query vector.
     *
     * @return list<float>
     */
    private function embed(string $text): array
    {
        $url = (string) config('qdrant.embedding_url');

        try {
            $response = Http::timeout(5)->post($url, ['text' => $text]);
            /** @var list<float> $vector */
            $vector = $response->successful() ? ($response->json('vector') ?? []) : [];
            return $vector;
        } catch (\Throwable $e) {
            Log::warning('QdrantRetriever: embed failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
