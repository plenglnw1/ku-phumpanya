<?php

declare(strict_types=1);

namespace Tests\Unit\GraphRag;

use App\Services\GraphRag\HybridRetriever;
use PHPUnit\Framework\TestCase;

class HybridRetrieverInterleaveTest extends TestCase
{
    public function test_takes_the_best_hit_of_each_source_in_turn(): void
    {
        $hits = [
            $this->hit('k1', 'KUKR', 10.0),
            $this->hit('k2', 'KUKR', 9.0),
            $this->hit('k3', 'KUKR', 8.0),
            $this->hit('f1', 'KU_Forest', 7.0),
            $this->hit('m1', 'KU_MOOC', 6.0),
            $this->hit('f2', 'KU_Forest', 5.0),
        ];

        $picked = HybridRetriever::interleaveSources($hits, 5);

        $this->assertSame(['k1', 'f1', 'm1', 'k2', 'f2'], array_column($picked, 'id'));
    }

    public function test_leaves_out_sources_whose_best_hit_is_far_below_the_top(): void
    {
        $hits = [
            $this->hit('k1', 'KUKR', 10.0),
            $this->hit('k2', 'KUKR', 9.0),
            $this->hit('m1', 'KU_MOOC', 1.0),
        ];

        $picked = HybridRetriever::interleaveSources($hits, 3);

        $this->assertSame(['k1', 'k2'], array_column($picked, 'id'));
    }

    public function test_returns_nothing_for_no_hits(): void
    {
        $this->assertSame([], HybridRetriever::interleaveSources([], 6));
    }

    /**
     * @return array<string, mixed>
     */
    private function hit(string $id, string $source, float $score): array
    {
        return ['id' => $id, 'source' => $source, '_score' => $score];
    }
}
