<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Str;

final class HybridRetriever
{
    public function __construct(
        private readonly ElasticClientFactory $factory,
        private readonly QdrantRetriever $qdrant,
    ) {}

    /**
     * Priority: Qdrant (vector) → Elasticsearch (keyword) → config fallback
     *
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $query, int $size = 5): array
    {
        if (config('qdrant.enabled')) {
            $results = $this->qdrant->retrieve($query, $size);
            if (! empty($results)) {
                return $results;
            }
        }

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
                            // Must match the ku_bcg_documents mapping. `content`, `topic`,
                            // `faculty_tags` and `bcg_tags` are the normalized names used
                            // downstream by RetrievalLinker — they do not exist in the index,
                            // so querying them matched nothing. `bcg_pillars`/`faculties`/
                            // `search_keyword` are keyword-typed and excluded on purpose:
                            // they only match exact tokens, never free text.
                            'fields' => [
                                'title^3',
                                'title_en^2',
                                'keywords^2',
                                'topic_names_th',
                                'topic_names_en',
                                'abstract',
                            ],
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
     * Relation triples for the knowledge graph, from the `ku_bcg_relations` index.
     *
     * Without this the graph falls back to `graphrag_seed`, which only links a topic
     * to its faculties and courses — a star with no traversable paths. The index
     * holds the real cross-source triples (mitigatesCarbonVia, pollutesVia, …).
     *
     * @return list<array<string, mixed>>
     */
    public function retrieveRelations(string $query, int $size = 24): array
    {
        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return [];
        }

        try {
            $response = $client->search([
                'index' => config('elasticsearch.indices.relations'),
                'body' => [
                    'size' => $size,
                    'query' => [
                        'bool' => [
                            'should' => [
                                ['multi_match' => [
                                    'query' => $query,
                                    'fields' => ['subject^2', 'object^2', 'predicate'],
                                    'type' => 'best_fields',
                                ]],
                                // Relations are few and globally meaningful, so a query that
                                // matches none still yields the graph rather than nothing.
                                ['match_all' => (object) []],
                            ],
                        ],
                    ],
                ],
            ])->asArray();
        } catch (\Throwable) {
            return [];
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

