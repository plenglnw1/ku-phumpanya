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
            'faculty_tags' => ['วนศาสตร์', 'เกษตร', 'อุตสาหกรรมเกษตร', 'ประมง'],
            'sources' => [
                ['source' => 'KU_Forest', 'url' => 'https://research.ku.ac.th/forest/Project.aspx?ProjectNumber=1631188000&BudgetYear=2016', 'section' => 'project'],
                ['source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/KUKR/Detail/info/20020334', 'section' => 'detail'],
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
            'faculty_tags' => ['ประมง', 'อุตสาหกรรมเกษตร', 'เกษตร'],
            'sources' => [
                ['source' => 'KU_Forest', 'url' => 'https://research.ku.ac.th/forest/Project.aspx?ProjectNumber=2040055003&BudgetYear=2020', 'section' => 'project'],
                ['source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/KUKR/Detail/info/448215', 'section' => 'detail'],
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
            'faculty_tags' => ['ประมง', 'อุตสาหกรรมเกษตร', 'เกษตร'],
            'sources' => [
                ['source' => 'KU_Forest', 'url' => 'https://research.ku.ac.th/forest/Project.aspx?ProjectNumber=2630485000&BudgetYear=2026', 'section' => 'project'],
                ['source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/KUKR/Detail/info/20020747', 'section' => 'detail'],
                ['source' => 'KU_MOOC', 'url' => 'https://kumooc.ku.th/courses/84/info', 'section' => 'course'],
            ],
            'courses' => [
                ['course_id' => 'kumooc026', 'title' => 'เศรษฐศาสตร์การจัดการมลพิษและของเสีย', 'url' => 'https://kumooc.ku.th/courses/84/info'],
            ],
        ],
    ],
];
