<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Str;

final class HybridRetriever
{
    public function __construct(
        private readonly ElasticClientFactory $factory,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $query, int $size = 5): array
    {
        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return $this->fallbackRetrieve($query, $size);
        }

        try {
            $index = config('elasticsearch.indices.docs');
            $response = $client->search([
                'index' => $index,
                'body' => [
                    'size' => $size,
                    'query' => [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => ['title^3', 'content^2', 'topic', 'faculty_tags', 'bcg_tags'],
                            'type' => 'best_fields',
                        ],
                    ],
                ],
            ])->asArray();
        } catch (\Throwable) {
            return $this->fallbackRetrieve($query, $size);
        }

        return array_map(
            static fn (array $hit): array => array_merge(['_score' => $hit['_score'] ?? 0.0], $hit['_source'] ?? []),
            $response['hits']['hits'] ?? [],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackRetrieve(string $query, int $size): array
    {
        $terms = collect(explode(' ', Str::lower($query)))
            ->filter()
            ->values();

        $topics = config('graphrag_seed.topics', []);
        $docs = [];
        foreach ($topics as $topic) {
            foreach (($topic['sources'] ?? []) as $source) {
                $text = Str::lower(($topic['title'] ?? '').' '.($topic['summary'] ?? '').' '.($source['source'] ?? ''));
                $score = $terms->reduce(
                    fn (int $carry, string $term): int => $carry + (Str::contains($text, $term) ? 1 : 0),
                    0,
                );

                $docs[] = [
                    '_score' => $score,
                    'doc_id' => sha1($text),
                    'topic' => $topic['topic_id'],
                    'title' => $topic['title'],
                    'content' => $topic['summary'],
                    'source' => $source['source'],
                    'url' => $source['url'],
                    'faculty_tags' => $topic['faculty_tags'],
                    'bcg_tags' => $topic['bcg_tags'],
                ];
            }
        }

        return collect($docs)
            ->sortByDesc('_score')
            ->take($size)
            ->values()
            ->all();
    }
}

