<?php

declare(strict_types=1);

/*
 * Ground truth for `php artisan eval:retrieval` (offline Recall@K of HybridRetriever).
 *
 * A topic is judged by `relevant_urls` when experts have listed them (strict recall);
 * otherwise any document tagged with `topic_id` in ku_bcg_documents counts as relevant,
 * which is an approximation until URLs are curated. The seed URLs come from
 * config/graphrag_seed.php.
 */
return [
    'k' => 10,

    'topics' => [
        '1' => [
            'query' => 'ชีวมวลและพลังงานทดแทนจากผลผลิตเกษตร',
            'relevant_urls' => [],
        ],
        '2' => [
            'query' => 'ไคโตซานบรรจุภัณฑ์ชีวภาพย่อยสลายได้',
            'relevant_urls' => [
                'https://research.ku.ac.th/forest/Project.aspx?ProjectNumber=2630485000&BudgetYear=2026',
                'https://kukr.lib.ku.ac.th/KUKR/Detail/info/20020747',
                'https://kumooc.ku.th/courses/84/info',
            ],
        ],
        '3' => [
            'query' => 'ไมโครพลาสติกกับคุณภาพน้ำ',
            'relevant_urls' => [
                'https://research.ku.ac.th/forest/Project.aspx?ProjectNumber=2040055003&BudgetYear=2020',
                'https://kukr.lib.ku.ac.th/KUKR/Detail/info/448215',
                'https://kumooc.ku.th/courses/82/info',
            ],
        ],
        '4' => [
            'query' => 'คาร์บอนฟุตพริ้นท์ภาคเกษตรและป่าไม้',
            'relevant_urls' => [
                'https://research.ku.ac.th/forest/Project.aspx?ProjectNumber=1631188000&BudgetYear=2016',
                'https://kukr.lib.ku.ac.th/KUKR/Detail/info/20020334',
                'https://kumooc.ku.th/courses/6/info',
            ],
        ],
        '5' => [
            'query' => 'การเพาะเลี้ยงสัตว์น้ำยั่งยืน โปรไบโอติก',
            'relevant_urls' => [],
        ],
        '6' => [
            'query' => 'วนเกษตรและความหลากหลายทางชีวภาพ',
            'relevant_urls' => [],
        ],
    ],
];
