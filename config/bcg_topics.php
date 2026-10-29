<?php

declare(strict_types=1);

/*
| Pilot faculties per BCG topic, keyed by the corpus `topic_ids`. Corpus documents
| carry topic ids but no faculty field, so result tags resolve faculties here.
| Source: KU-BCG elastic-search/seed-data.json (topics[].faculties).
*/
return [
    'faculties' => [
        '1' => ['เกษตร', 'อุตสาหกรรมเกษตร', 'วนศาสตร์'],
        '2' => ['ประมง', 'อุตสาหกรรมเกษตร', 'เกษตร'],
        '3' => ['ประมง', 'อุตสาหกรรมเกษตร', 'เกษตร'],
        '4' => ['วนศาสตร์', 'เกษตร', 'อุตสาหกรรมเกษตร', 'ประมง'],
        '5' => ['ประมง', 'อุตสาหกรรมเกษตร'],
        '6' => ['วนศาสตร์', 'เกษตร', 'ประมง'],
    ],
];
