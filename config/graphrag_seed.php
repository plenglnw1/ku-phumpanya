<?php

declare(strict_types=1);

return [
    'suggestions' => [
        'carbon footprint in agriculture',
        'microplastics water quality',
        'chitosan biodegradable packaging',
    ],
    'topics' => [
        [
            'topic_id' => 'carbon-footprint-carbon-neutrality',
            'title' => 'Carbon Footprint และ Carbon Neutrality ภาคเกษตร-ป่าไม้',
            'summary' => 'หัวข้อศูนย์กลางที่เชื่อมงานวิจัยการลดคาร์บอนของภาคเกษตร ป่าไม้ และอุตสาหกรรมเกษตร',
            'bcg_tags' => ['Green'],
            'faculty_tags' => ['เกษตร', 'อุตสาหกรรมเกษตร', 'วนศาสตร์'],
            'sources' => [
                ['source' => 'KU_Forest', 'url' => 'https://research.ku.ac.th/forest/Search.aspx?keyword=Climate%20change', 'section' => 'keyword'],
                ['source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/KUKR/Search', 'section' => 'search'],
                ['source' => 'KU_MOOC', 'url' => 'https://kumooc.ku.th/courses/6/info', 'section' => 'course'],
            ],
            'courses' => [
                ['course_id' => 'kumooc005', 'title' => 'การคำนวณคาร์บอนฟุตพริ้นท์รายบุคคล', 'url' => 'https://kumooc.ku.th/courses/6/info'],
                ['course_id' => 'kumooc016', 'title' => 'พื้นฐานความรู้การทำฟาร์มป่าไม้', 'url' => 'https://kumooc.ku.th/courses/73/info'],
            ],
        ],
        [
            'topic_id' => 'microplastics-water-quality',
            'title' => 'Microplastics กับคุณภาพน้ำ',
            'summary' => 'การติดตามผลกระทบไมโครพลาสติกต่อระบบนิเวศน้ำและห่วงโซ่อาหาร',
            'bcg_tags' => ['Green', 'Circular'],
            'faculty_tags' => ['เกษตร', 'อุตสาหกรรมเกษตร', 'วนศาสตร์'],
            'sources' => [
                ['source' => 'KU_Forest', 'url' => 'https://research.ku.ac.th/forest/Search.aspx?keyword=water%20quality', 'section' => 'keyword'],
                ['source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/KUKR/Search', 'section' => 'search'],
                ['source' => 'KU_MOOC', 'url' => 'https://kumooc.ku.th/courses/82/info', 'section' => 'course'],
            ],
            'courses' => [
                ['course_id' => 'kumooc025', 'title' => 'Microplastics 101', 'url' => 'https://kumooc.ku.th/courses/82/info'],
                ['course_id' => 'kumooc026', 'title' => 'เศรษฐศาสตร์การจัดการมลพิษและของเสีย', 'url' => 'https://kumooc.ku.th/courses/84/info'],
            ],
        ],
        [
            'topic_id' => 'chitosan-bio-packaging',
            'title' => 'Chitosan และบรรจุภัณฑ์ชีวภาพ',
            'summary' => 'การใช้ไคโตซานจากของเหลืออุตสาหกรรมอาหารเพื่อบรรจุภัณฑ์ย่อยสลายได้',
            'bcg_tags' => ['Bio', 'Circular'],
            'faculty_tags' => ['เกษตร', 'อุตสาหกรรมเกษตร', 'วนศาสตร์'],
            'sources' => [
                ['source' => 'KU_Forest', 'url' => 'https://research.ku.ac.th/forest/Search.aspx?keyword=chitosan', 'section' => 'keyword'],
                ['source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/KUKR/Search', 'section' => 'search'],
                ['source' => 'KU_MOOC', 'url' => 'https://kumooc.ku.th/courses/list/10', 'section' => 'category'],
            ],
            'courses' => [
                ['course_id' => 'kumooc010', 'title' => 'พื้นฐานเคมีและวัสดุสำหรับบรรจุภัณฑ์', 'url' => 'https://kumooc.ku.th/courses/list/10'],
            ],
        ],
    ],
];

