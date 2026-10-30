<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GraphRag\Agent\LearningUnitOrder;
use App\Services\GraphRag\Agent\Synthesizer;
use Tests\TestCase;

class LearningUnitOrderTest extends TestCase
{
    /**
     * @param  list<string>  $teaches
     * @param  list<string>  $needs
     * @return array<string, mixed>
     */
    private static function unit(string $title, array $teaches, array $needs, int $difficulty = 1): array
    {
        return [
            'url' => "https://x/{$title}", 'title' => $title, 'source' => 'KU_MOOC',
            'teaches' => $teaches, 'needs' => $needs, 'difficulty' => $difficulty, 'concept_source' => 'ai',
        ];
    }

    /**
     * @param  array{ordered: bool, phases: list<list<array<string, mixed>>>}  $order
     * @return list<list<string>>
     */
    private static function titles(array $order): array
    {
        return array_map(fn (array $phase): array => array_column($phase, 'title'), $order['phases']);
    }

    public function test_a_unit_that_needs_what_another_teaches_comes_later(): void
    {
        $order = LearningUnitOrder::order([self::unit('C', ['c'], ['b']), self::unit('B', ['b'], ['a']), self::unit('A', ['a'], [])]);

        $this->assertTrue($order['ordered']);
        $this->assertSame([['A'], ['B'], ['C']], self::titles($order));
    }

    public function test_the_phase_is_the_longest_chain(): void
    {
        $order = LearningUnitOrder::order([self::unit('A', ['a'], []), self::unit('B', ['b'], ['a']), self::unit('C', ['c'], ['a', 'b'])]);

        $this->assertSame([['A'], ['B'], ['C']], self::titles($order));
    }

    public function test_difficulty_orders_within_a_phase_only(): void
    {
        $order = LearningUnitOrder::order([self::unit('hard', ['h'], [], 3), self::unit('easy', ['e'], [], 1), self::unit('late', ['l'], ['h'], 1)]);

        $this->assertSame([['easy', 'hard'], ['late']], self::titles($order));
    }

    public function test_independent_units_are_not_ordered_and_a_cycle_does_not_hang(): void
    {
        $this->assertFalse(LearningUnitOrder::order([self::unit('A', ['a'], []), self::unit('B', ['b'], [])])['ordered']);
        $this->assertFalse(LearningUnitOrder::order([])['ordered']);
        $this->assertTrue(LearningUnitOrder::order([self::unit('A', ['a'], ['b']), self::unit('B', ['b'], ['a'])])['ordered']);
    }

    public function test_synthesizer_puts_ordered_courses_first_and_groups_the_rest_by_topic(): void
    {
        $docs = [
            ['title' => 'Research', 'source' => 'KUKR', 'url' => 'https://kukr/1', 'topic_names_th' => ['คาร์บอน']],
            self::unit('Neutrality', ['carbon_neutrality'], ['carbon_footprint_basics'], 2),
            self::unit('Footprint', ['carbon_footprint_basics'], []),
        ];

        $phases = app(Synthesizer::class)->groupIntoPhases($docs);

        $this->assertSame(['ขั้นที่ 1 · ปูพื้นฐาน', 'ขั้นที่ 2 · ต่อยอด', 'คาร์บอน'], array_column($phases, 'name'));
        $module = $phases[1]['modules'][0];
        $this->assertSame(['carbon_neutrality'], $module['teaches']);
        $this->assertSame(['carbon_footprint_basics'], $module['needs']);
        $this->assertSame(2, $module['difficulty']);
        $this->assertSame('ai', $module['concept_source']);
        $this->assertArrayNotHasKey('teaches', $phases[2]['modules'][0]);
    }

    public function test_without_dependencies_courses_stay_in_topic_groups(): void
    {
        $docs = [
            self::unit('A', ['a'], []) + ['topic_names_th' => ['ป่าไม้']],
            self::unit('B', ['b'], []) + ['topic_names_th' => ['ป่าไม้']],
        ];

        $phases = app(Synthesizer::class)->groupIntoPhases($docs);

        $this->assertSame(['ป่าไม้'], array_column($phases, 'name'));
        $this->assertSame(['a'], $phases[0]['modules'][0]['teaches']);
    }
}
