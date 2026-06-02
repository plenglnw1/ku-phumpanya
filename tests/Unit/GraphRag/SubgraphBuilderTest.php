<?php

declare(strict_types=1);

namespace Tests\Unit\GraphRag;

use App\Services\GraphRag\SubgraphBuilder;
use Tests\TestCase;

class SubgraphBuilderTest extends TestCase
{
    public function test_it_builds_graph_with_edges_from_fallback_seed(): void
    {
        config()->set('elasticsearch.enabled', false);

        $builder = app(SubgraphBuilder::class);
        $graph = $builder->build(['Carbon Footprint และ Carbon Neutrality ภาคเกษตร-ป่าไม้']);

        $this->assertArrayHasKey('nodes', $graph);
        $this->assertArrayHasKey('edges', $graph);
        $this->assertNotEmpty($graph['nodes']);
        $this->assertNotEmpty($graph['edges']);
    }
}

