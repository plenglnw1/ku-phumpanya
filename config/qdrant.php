<?php

return [
    'enabled' => env('QDRANT_ENABLED', false),
    'host' => env('QDRANT_HOST', 'http://localhost:6333'),
    'api_key' => env('QDRANT_API_KEY', ''),

    'collections' => [
        'docs' => env('QDRANT_COLLECTION_DOCS', 'ku_phumpanya_docs'),
        'relations' => env('QDRANT_COLLECTION_RELATIONS', 'ku_phumpanya_relations'),
    ],

    'embedding_url' => env('QDRANT_EMBEDDING_URL', 'http://localhost:8765/embed'),
    'top_k' => (int) env('QDRANT_TOP_K', 6),
];
