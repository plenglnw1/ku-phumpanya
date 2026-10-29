<?php

declare(strict_types=1);

namespace Tests\Unit\GraphRag;

use App\Services\GraphRag\Agent\ResultFormatter;
use PHPUnit\Framework\TestCase;

class ResultFormatterTagsTest extends TestCase
{
    public function test_collects_tags_most_frequent_first(): void
    {
        $tags = ResultFormatter::tags([
            ['bcg_tags' => ['Green'], 'faculty_tags' => ['วนศาสตร์']],
            ['bcg_pillars' => ['Bio', 'Green'], 'faculties' => ['เกษตร', 'วนศาสตร์']],
            ['bcg_tags' => [], 'faculty_tags' => ['']],
        ]);

        $this->assertSame(['Green', 'Bio'], $tags['bcg_pillars']);
        $this->assertSame(['วนศาสตร์', 'เกษตร'], $tags['faculties']);
    }

    public function test_empty_documents_give_empty_tags(): void
    {
        $this->assertSame(['bcg_pillars' => [], 'faculties' => []], ResultFormatter::tags([]));
    }
}
