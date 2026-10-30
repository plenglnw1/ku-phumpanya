<?php

declare(strict_types=1);

namespace Tests\Unit\GraphRag;

use App\Services\GraphRag\Agent\ResultFormatter;
use App\Services\GraphRag\RelationGraphBuilder;
use PHPUnit\Framework\TestCase;

class AiFieldsAndEdgeSelectionTest extends TestCase
{
    private const AI = ['model' => 'claude-sonnet-5-5', 'prompt_version' => 'v1', 'source_lang' => 'en', 'fields' => ['title'], 'reviewed' => false];

    public function test_evidence_carries_ai_translations_only_when_present(): void
    {
        $evidence = ResultFormatter::toEvidence([
            ['title' => 'Biochar', 'source' => 'KUKR', 'url' => 'u1', 'abstract' => 'English abstract', 'title_ai_th' => 'ถ่านชีวภาพ', 'abstract_ai_th' => 'บทคัดย่อภาษาไทย', 'ai_translation' => self::AI],
            ['title' => 'ถ่าน', 'source' => 'KUKR', 'url' => 'u2', 'abstract' => 'ไทย'],
        ]);

        $this->assertSame('ถ่านชีวภาพ', $evidence[0]['title_ai_th']);
        $this->assertSame('บทคัดย่อภาษาไทย', $evidence[0]['snippet_ai_th']);
        $this->assertSame(self::AI, $evidence[0]['ai_translation']);
        $this->assertSame(['title', 'source', 'url', 'snippet'], array_keys($evidence[1]));
    }

    public function test_keyword_translations_stay_aligned_or_are_dropped(): void
    {
        $aligned = ResultFormatter::keywords(['keywords' => ['rice', '', 'straw'], 'keywords_ai_th' => ['ข้าว', 'x', 'ฟาง']], 8);
        $this->assertSame(['rice', 'straw'], $aligned['keywords']);
        $this->assertSame(['ข้าว', 'ฟาง'], $aligned['keywords_ai_th']);

        $shifted = ResultFormatter::keywords(['keywords' => ['rice', 'straw'], 'keywords_ai_th' => ['ข้าว']], 8);
        $this->assertSame(['keywords' => ['rice', 'straw']], $shifted);
    }

    public function test_llm_modules_get_translations_from_their_document(): void
    {
        $path = ResultFormatter::withDocumentFields(
            ['phases' => [['name' => 'p', 'modules' => [['title' => 'Biochar', 'url' => 'u1'], ['title' => 'other', 'url' => 'zz']]]]],
            [['url' => 'u1', 'title_ai_th' => 'ถ่านชีวภาพ', 'ai_translation' => self::AI]],
        );

        $this->assertSame('ถ่านชีวภาพ', $path['phases'][0]['modules'][0]['title_ai_th']);
        $this->assertArrayNotHasKey('title_ai_th', $path['phases'][0]['modules'][1]);
    }

    public function test_curated_edges_are_kept_and_weak_edges_trimmed_without_islands(): void
    {
        $edges = [
            ['from' => 'X', 'to' => 'Y', 'type' => 'relatedTo', 'origin' => 'derived', 'weight' => 0.99],
            ['from' => 'A', 'to' => 'B', 'type' => 'requires'],
            ['from' => 'B', 'to' => 'C', 'type' => 'relatedTo', 'origin' => 'ai_suggested', 'confidence' => 0.9],
            ['from' => 'B', 'to' => 'D', 'type' => 'relatedTo', 'origin' => 'derived', 'weight' => 0.5],
            ['from' => 'C', 'to' => 'E', 'type' => 'relatedTo', 'origin' => 'derived', 'weight' => 0.2],
        ];

        $picked = RelationGraphBuilder::selectEdges($edges, ['A'], 10, 3);

        $this->assertSame(['A>B', 'B>D', 'B>C'], array_map(fn (array $e): string => $e['from'].'>'.$e['to'], $picked));
    }

    public function test_node_budget_is_respected_for_non_curated_edges(): void
    {
        $edges = [
            ['from' => 'A', 'to' => 'B', 'type' => 'requires'],
            ['from' => 'B', 'to' => 'C', 'type' => 'relatedTo', 'origin' => 'derived', 'weight' => 1],
            ['from' => 'A', 'to' => 'B', 'type' => 'relatedTo', 'origin' => 'ai_suggested', 'confidence' => 0.1],
        ];

        $picked = RelationGraphBuilder::selectEdges($edges, ['A'], 2, 10);

        $this->assertCount(2, $picked);
        $this->assertSame('ai_suggested', $picked[1]['origin']);
    }
}
