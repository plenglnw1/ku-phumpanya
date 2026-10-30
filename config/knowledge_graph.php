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

    // Markdown reports published by the KU-BCG import (deploy/neo4j-ku/kg-ctl.sh import).
    'reports_path' => env('KG_REPORTS_PATH', ''),
    'reports' => [
        'neo4j-verify' => 'Gate verification (1.2.1–1.2.4)',
    ],

    // Graph size per search. `relations` is how many triples retrieval fetches; the
    // tiers then cap nodes and edges. Curated edges are kept first, then derived
    // (by weight), then AI-suggested (by confidence), and only edges that join the
    // graph already built are added, so trimming never leaves floating islands.
    'limits' => [
        'relations' => (int) env('KG_RELATIONS_PER_SEARCH', 60),
        'basic' => ['nodes' => 20, 'edges' => 30],
        'intermediate' => ['nodes' => 40, 'edges' => 60],
        'advanced' => ['nodes' => 80, 'edges' => 120],
    ],

    // Edges without `origin` were curated by people and are searched ahead of the rest.
    'curated_relation_boost' => 3.0,

    // The six BCG topics; ids match `topic_id` in ku_bcg_relations and `topic_ids` in ku_bcg_documents.
    'topics' => [
        '1' => 'ชีวมวลและพลังงานทดแทนจากผลผลิตเกษตร',
        '2' => 'ไคโตซานและบรรจุภัณฑ์ชีวภาพย่อยสลายได้',
        '3' => 'Microplastics กับคุณภาพน้ำในระบบนิเวศและการเพาะเลี้ยง',
        '4' => 'Carbon Footprint และการมุ่งสู่ Carbon Neutrality ภาคเกษตร-ป่าไม้',
        '5' => 'การเพาะเลี้ยงสัตว์น้ำที่ยั่งยืนและ Probiotic ในระบบเลี้ยง',
        '6' => 'วนเกษตร (Agroforestry) และความหลากหลายทางชีวภาพ',
    ],

    // Same wording as KU-BCG lib/kgText.ts so admins and users read the same phrases.
    'predicate_labels' => [
        'mitigatesCarbonVia' => 'ช่วยลดคาร์บอนผ่าน',
        'integratesSpecies' => 'ผสมผสานเข้ากับ',
        'isKeywordOf' => 'เป็นคำสำคัญของ',
        'pollutesVia' => 'ก่อมลพิษผ่าน',
        'isEntryPointFor' => 'เป็นคอร์สเริ่มต้นสำหรับ',
        'isFeedstockOf' => 'เป็นวัตถุดิบของ',
        'providesRawMaterialTo' => 'ส่งวัตถุดิบให้',
        'isLaunchingNodeFor' => 'เป็นคอร์สปูพื้นฐานให้',
        'expandsEconomicsPerspectiveOf' => 'ขยายมุมมองด้านเศรษฐศาสตร์ของ',
        'enhancesGrowthOf' => 'ช่วยเพิ่มการเติบโตของ',
        'requires' => 'ต้องอาศัย',
        'connectsViaValueChain' => 'เชื่อมโยงกันผ่านห่วงโซ่คุณค่ากับ',
        'relatedTo' => 'เกี่ยวข้องกับ',
        'linkedFaculty' => 'เกี่ยวข้องกับคณะ',
        'studiedBy' => 'มีการศึกษาโดย',
        'isCourseFor' => 'เป็นชุดวิชาสำหรับ',
        'inTopic' => 'อยู่ในหัวข้อ',
    ],

    'source_labels' => [
        'KUKR' => 'งานวิจัย KUKR',
        'KU_Forest' => 'โครงการ KU Forest',
        'KU_MOOC' => 'คอร์ส KU MOOC',
        'KU_NonDegree' => 'ชุดวิชา Non-Degree',
    ],
];
