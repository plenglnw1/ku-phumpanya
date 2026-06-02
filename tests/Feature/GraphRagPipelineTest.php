<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\GraphRag\GraphRagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphRagPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_rag_service_returns_payload_for_three_mock_queries(): void
    {
        config()->set('elasticsearch.enabled', false);

        $service = app(GraphRagService::class);
        $queries = [
            'carbon footprint in agriculture',
            'microplastics water quality',
            'chitosan biodegradable packaging',
        ];

        foreach ($queries as $query) {
            $payload = $service->search($query);

            $this->assertArrayHasKey('overview', $payload);
            $this->assertArrayHasKey('knowledge_graph', $payload);
            $this->assertArrayHasKey('learning_path', $payload);
            $this->assertNotEmpty($payload['knowledge_graph']['edges']);
        }
    }

    public function test_search_show_returns_graphrag_result_and_evidence(): void
    {
        config()->set('elasticsearch.enabled', false);

        $user = User::factory()->create(['role' => UserRole::Student]);
        $history = SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => 'carbon footprint in agriculture',
        ]);

        $this->actingAs($user)
            ->get(route('search.show', $history))
            ->assertOk()
            ->assertSee('GraphRAG result')
            ->assertSee('Evidence from KU sources');
    }
}

