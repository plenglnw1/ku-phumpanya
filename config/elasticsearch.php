<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('ELASTICSEARCH_ENABLED', true),
    'host' => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
    'username' => env('ELASTICSEARCH_USERNAME'),
    'password' => env('ELASTICSEARCH_PASSWORD'),
    'api_key' => env('ELASTICSEARCH_API_KEY'),

    'indices' => [
        'docs' => env('ELASTICSEARCH_INDEX_DOCS', 'ku_docs'),
        'triples' => env('ELASTICSEARCH_INDEX_TRIPLES', 'ku_triples'),
        'entity_links' => env('ELASTICSEARCH_INDEX_ENTITY_LINKS', 'ku_entity_links'),
        'learning_paths' => env('ELASTICSEARCH_INDEX_LEARNING_PATHS', 'ku_learning_paths'),
    ],
];

