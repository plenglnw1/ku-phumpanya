<?php

declare(strict_types=1);

namespace Tests\Unit\GraphRag;

use App\Services\GraphRag\DocumentSource;
use App\Services\GraphRag\ElasticClientFactory;
use App\Services\GraphRag\RelationGraphBuilder;
use PHPUnit\Framework\TestCase;

class NonDegreeSourceTest extends TestCase
{
    public function test_moocs_and_non_degree_courses_count_as_courses(): void
    {
        $this->assertTrue(DocumentSource::isCourse(['source' => 'KU_MOOC']));
        $this->assertTrue(DocumentSource::isCourse(['source' => 'KU_NonDegree']));
        $this->assertFalse(DocumentSource::isCourse(['source' => 'KUKR']));
        $this->assertFalse(DocumentSource::isCourse([]));
    }

    public function test_english_abstracts_are_searched(): void
    {
        $this->assertContains('abstract_en', DocumentSource::SEARCH_FIELDS);
    }

    public function test_course_for_subjects_become_course_nodes(): void
    {
        $graph = (new RelationGraphBuilder(new ElasticClientFactory))->build(
            ['Bioplastics'],
            [
                ['subject' => 'ชุดวิชาการจัดการขยะพลาสติก', 'predicate' => 'isCourseFor', 'object' => 'Bioplastics'],
                ['subject' => 'Bioplastics', 'predicate' => 'relatedTo', 'object' => 'BCG_Circular'],
            ],
        );

        $types = array_column($graph['nodes'], 'type', 'label');

        $this->assertSame('course', $types['ชุดวิชาการจัดการขยะพลาสติก']);
        $this->assertSame('topic', $types['Bioplastics']);
        $this->assertSame('bcg_pillar', $types['BCG_Circular']);
        $this->assertStringContainsString('Non-Degree', $graph['description']);
    }
}
