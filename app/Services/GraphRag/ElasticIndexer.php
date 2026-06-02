<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Elastic\Elasticsearch\Client;

final class ElasticIndexer
{
    public function __construct(
        private readonly ElasticClientFactory $factory,
    ) {}

    public function createIndices(): void
    {
        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return;
        }

        try {
            $indices = config('elasticsearch.indices');

            $this->createIndexIfMissing($client, $indices['docs'], [
                'mappings' => [
                    'properties' => [
                        'doc_id' => ['type' => 'keyword'],
                        'topic' => ['type' => 'keyword'],
                        'title' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                        'source' => ['type' => 'keyword'],
                        'section' => ['type' => 'keyword'],
                        'url' => ['type' => 'keyword'],
                        'faculty_tags' => ['type' => 'keyword'],
                        'bcg_tags' => ['type' => 'keyword'],
                        'embedding' => [
                            'type' => 'dense_vector',
                            'dims' => 384,
                            'index' => true,
                            'similarity' => 'cosine',
                            'index_options' => ['type' => 'int8_hnsw'],
                        ],
                    ],
                ],
            ]);

            $this->createIndexIfMissing($client, $indices['triples'], [
                'mappings' => [
                    'properties' => [
                        'triple_id' => ['type' => 'keyword'],
                        'subject' => ['type' => 'keyword'],
                        'predicate' => ['type' => 'keyword'],
                        'object' => ['type' => 'keyword'],
                        'source' => ['type' => 'keyword'],
                        'url' => ['type' => 'keyword'],
                        'topic' => ['type' => 'keyword'],
                        'confidence' => ['type' => 'float'],
                    ],
                ],
            ]);

            $this->createIndexIfMissing($client, $indices['entity_links'], [
                'mappings' => [
                    'properties' => [
                        'left_entity' => ['type' => 'keyword'],
                        'right_entity' => ['type' => 'keyword'],
                        'relation' => ['type' => 'keyword'],
                        'weight' => ['type' => 'integer'],
                        'topic' => ['type' => 'keyword'],
                    ],
                ],
            ]);

            $this->createIndexIfMissing($client, $indices['learning_paths'], [
                'mappings' => [
                    'properties' => [
                        'path_id' => ['type' => 'keyword'],
                        'topic' => ['type' => 'keyword'],
                        'title' => ['type' => 'text'],
                        'description' => ['type' => 'text'],
                        'courses' => ['type' => 'nested'],
                        'faculty_tags' => ['type' => 'keyword'],
                        'bcg_tags' => ['type' => 'keyword'],
                        'estimated_hours' => ['type' => 'keyword'],
                    ],
                ],
            ]);
        } catch (\Throwable) {
            // Local development may run Elasticsearch with auth disabled/enabled differently.
            // We fail-open so fallback retrieval can still serve UI and tests.
        }
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     */
    public function bulkIndex(string $index, array $docs): void
    {
        $client = $this->factory->make();
        if (! $client instanceof Client || count($docs) === 0) {
            return;
        }

        $body = [];
        foreach ($docs as $doc) {
            $body[] = ['index' => ['_index' => $index]];
            $body[] = $doc;
        }

        try {
            $client->bulk(['body' => $body]);
            $client->indices()->refresh(['index' => $index]);
        } catch (\Throwable) {
            // Fail-open for environments without reachable/authenticated Elasticsearch.
        }
    }

    private function createIndexIfMissing(Client $client, string $index, array $schema): void
    {
        $exists = $client->indices()->exists(['index' => $index])->asBool();
        if ($exists) {
            return;
        }

        $client->indices()->create([
            'index' => $index,
            'body' => $schema,
        ]);
    }
}

