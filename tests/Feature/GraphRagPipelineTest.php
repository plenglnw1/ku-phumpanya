<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\GraphRag\GraphRagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GraphRagPipelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function fakeGeminiResponse(array $payload): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_graph_rag_service_returns_payload_for_three_queries(): void
    {
        config()->set('gemini.enabled', true);
        config()->set('gemini.api_key', 'test-key');
        config()->set('gemini.force_tier', 'basic');
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->fakeGeminiResponse([
                'title' => 'BCG Topic',
                'overview' => [
                    'intro' => 'Intro',
                    'analogy' => 'Analogy',
                    'research_basis' => 'KU research',
                    'expert' => 'KU expert',
                ],
                'learning_path' => [
                    'estimated_hours' => '90',
                    'subtitle' => 'Learning path',
                    'phases' => [
                        [
                            'name' => 'Phase 1',
                            'intro' => 'Start here',
                            'modules' => [],
                        ],
                    ],
                ],
            ])),
        ]);

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
            $this->assertArrayNotHasKey('progress', $payload['learning_path']);
            $this->assertSame('basic', $payload['tier']);
        }
    }

    public function test_legacy_search_when_gemini_disabled(): void
    {
        config()->set('gemini.enabled', false);
        config()->set('elasticsearch.enabled', false);

        $service = app(GraphRagService::class);
        $payload = $service->search('carbon footprint in agriculture');

        $this->assertArrayHasKey('overview', $payload);
        $this->assertArrayHasKey('knowledge_graph', $payload);
        $this->assertArrayNotHasKey('tier', $payload);
        $this->assertArrayNotHasKey('progress', $payload['learning_path']);
    }

    public function test_search_show_returns_graphrag_result_and_evidence(): void
    {
        config()->set('gemini.enabled', false);
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
            ->assertSee('Evidence from KU sources')
            ->assertDontSee('mock progress');
    }

    public function test_learning_path_component_does_not_show_mock_progress(): void
    {
        $html = view('components.phumpanya.learning-path', [
            'path' => [
                'estimated_hours' => '120',
                'subtitle' => 'From KU sources',
                'phases' => [],
            ],
            'topicTitle' => 'Biomass',
        ])->render();

        $this->assertStringNotContainsString('mock progress', $html);
        $this->assertStringNotContainsString('% complete', $html);
    }
}
