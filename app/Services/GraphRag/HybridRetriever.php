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
                    'size' => max($size * 5, 30),
                    'query' => [
                        'multi_match' => [
                            'query' => $query,
                            // Must match the ku_bcg_documents mapping. `content`, `topic`,
                            // `faculty_tags` and `bcg_tags` are the normalized names used
                            // downstream by RetrievalLinker — they do not exist in the index,
                            // so querying them matched nothing. `bcg_pillars`/`faculties`/
                            // `search_keyword` are keyword-typed and excluded on purpose:
                            // they only match exact tokens, never free text.
                            'fields' => DocumentSource::SEARCH_FIELDS,
                            'type' => 'best_fields',
                        ],
                    ],
                    // Best hits of each source as well: KU MOOC is 6 of 612 documents
                    // and rarely reaches the global top hits even when it matches.
                    'aggs' => [
                        'by_source' => [
                            'terms' => ['field' => 'source', 'size' => 5],
                            'aggs' => ['top' => ['top_hits' => ['size' => $size]]],
                        ],
                    ],
                ],
            ])->asArray();
        } catch (\Throwable) {
            return $this->fallbackRetrieve($query, $size);
        }

        return self::interleaveSources(self::mergeSourceBuckets($response), $size);
    }

    /**
     * Global hits plus each source's top hits, de-duplicated and ordered by score.
     *
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    public static function mergeSourceBuckets(array $response): array
    {
        $hits = $response['hits']['hits'] ?? [];
        foreach ($response['aggregations']['by_source']['buckets'] ?? [] as $bucket) {
            array_push($hits, ...($bucket['top']['hits']['hits'] ?? []));
        }

        $merged = [];
        foreach ($hits as $hit) {
            $key = (string) ($hit['_id'] ?? $hit['_source']['url'] ?? spl_object_id((object) $hit));
            $merged[$key] ??= array_merge(['_score' => (float) ($hit['_score'] ?? 0.0)], $hit['_source'] ?? []);
        }

        $merged = array_values($merged);
        usort($merged, static fn (array $a, array $b): int => $b['_score'] <=> $a['_score']);

        return $merged;
    }

    /**
     * Best hits round-robin across sources, so evidence is not all KUKR (403 of the
     * 612 documents) whenever KUKR wins on score.
     *
     * Only hits scoring at least `$minRatio` of the top hit take part, so a source with
     * no good match is left out instead of padded in with an unrelated document.
     *
     * @param  list<array<string, mixed>>  $hits  ordered by score, descending
     * @return list<array<string, mixed>>
     */
    public static function interleaveSources(array $hits, int $size, float $minRatio = 0.3): array
    {
        if ($hits === []) {
            return [];
        }

        $floor = (float) ($hits[0]['_score'] ?? 0.0) * $minRatio;
        $bySource = [];
        foreach ($hits as $hit) {
            if ((float) ($hit['_score'] ?? 0.0) >= $floor) {
                $bySource[(string) ($hit['source'] ?? '')][] = $hit;
            }
        }

        $picked = [];
        while (count($picked) < $size && $bySource !== []) {
            foreach (array_keys($bySource) as $source) {
                $picked[] = array_shift($bySource[$source]);
                if ($bySource[$source] === []) {
                    unset($bySource[$source]);
                }
                if (count($picked) === $size) {
                    break;
                }
            }
        }

        return $picked;
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
            // Do NOT fall back to match_all — that always returned the same ~19 baked
            // triples (Biomass/Chitosan/Agroforestry…), so every search showed identical
            // graph nodes. Prefer query-matched relations only; empty is OK — the graph
            // builder still renders nodes from retrieved doc titles/keywords.
            $response = $client->search([
                'index' => config('elasticsearch.indices.relations'),
                'body' => [
                    'size' => $size,
                    'query' => [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => ['subject^2', 'object^2', 'predicate'],
                            'type' => 'best_fields',
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
