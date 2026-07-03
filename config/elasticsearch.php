<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('ELASTICSEARCH_ENABLED', true),
    'host' => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
    'username' => env('ELASTICSEARCH_USERNAME'),
    'password' => env('ELASTICSEARCH_PASSWORD'),
    'api_key' => env('ELASTICSEARCH_API_KEY'),

    'indices' => [
        'docs' => env('ELASTICSEARCH_INDEX_DOCS', 'ku_bcg_documents'),
        'triples' => env('ELASTICSEARCH_INDEX_TRIPLES', 'ku_triples'),
        'relations' => env('ELASTICSEARCH_INDEX_RELATIONS', 'ku_bcg_relations'),
        'entity_links' => env('ELASTICSEARCH_INDEX_ENTITY_LINKS', 'ku_entity_links'),
        'learning_paths' => env('ELASTICSEARCH_INDEX_LEARNING_PATHS', 'ku_learning_paths'),
    ],
];

