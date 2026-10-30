<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GraphRag\Agent\Synthesizer;
use Tests\TestCase;

class SynthesizerPhasesTest extends TestCase
{
    public function test_documents_without_abstract_keywords_or_authors_do_not_break_phases(): void
    {
        $docs = [
            ['title' => 'ชุดวิชาพลังงานชีวมวล', 'source' => 'KU_NonDegree', 'url' => 'https://ku.ac.th/nd/1', 'authors' => [], 'keywords' => null, 'abstract' => null, 'year' => 2026],
            ['title' => 'KUKR paper', 'source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/1', 'authors' => [['name' => 'สมชาย ใจดี']], 'keywords' => []],
            ['title' => 'Forest project', 'source' => 'KU_Forest', 'url' => 'https://research.ku.ac.th/1', 'authors' => 'ผู้วิจัย ก', 'keywords' => [['th' => 'ป่าไม้']]],
        ];

        $phases = app(Synthesizer::class)->groupIntoPhases($docs);

        $descriptions = collect($phases)->flatMap(fn (array $phase): array => array_column($phase['modules'] ?? [], 'desc'))->all();
        $this->assertContains('2026', $descriptions);
        $this->assertContains('สมชาย ใจดี', $descriptions);
        $this->assertContains('ป่าไม้', $descriptions);
    }
}
