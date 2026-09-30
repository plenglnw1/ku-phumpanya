<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('KG_ENABLED', false),

    // Neo4j HTTP API on the same host (bound to localhost; the KU firewall blocks it externally).
    'http_url' => env('KG_NEO4J_HTTP_URL', 'http://127.0.0.1:17474'),
    'database' => env('KG_NEO4J_DATABASE', 'neo4j'),
    'username' => env('KG_NEO4J_USERNAME', 'neo4j'),
    'password' => env('KG_NEO4J_PASSWORD', ''),
    'timeout' => (int) env('KG_NEO4J_TIMEOUT', 20),

    // Caps what one ad-hoc Cypher query may return to the browser.
    'row_limit' => (int) env('KG_ROW_LIMIT', 200),
    'node_limit' => (int) env('KG_NODE_LIMIT', 400),

    // Besides admins, these accounts (comma separated emails) may open /graph.
    'viewer_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('KG_VIEWER_EMAILS', '')),
    ))),

    // Markdown validation reports published by the knowledge-graph import (kg-ctl.sh import).
    'reports_path' => env('KG_REPORTS_PATH', ''),
    'reports' => [
        'graph-validation' => 'Graph validation (1.2.1–1.2.4)',
        'owl-validation' => 'OWL validation (1.2.5)',
        'ioc' => 'IOC (1.2.5)',
    ],
];
