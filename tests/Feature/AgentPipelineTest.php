<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GraphRag\Agent\AgentPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentPipelineTest extends TestCase
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

    public function test_basic_tier_returns_unified_shape(): void
    {
        config()->set('gemini.enabled', true);
        config()->set('gemini.api_key', 'test-key');
        config()->set('gemini.force_tier', 'basic');
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->fakeGeminiResponse([
                'title' => 'Biomass Research',
                'overview' => [
                    'intro' => 'Biomass intro',
                    'analogy' => 'Like recycling',
                    'research_basis' => 'KU papers',
                    'expert' => 'KU expert',
                ],
                'learning_path' => [
                    'estimated_hours' => '90',
                    'subtitle' => 'Test path',
                    'phases' => [],
                ],
            ])),
        ]);

        $result = app(AgentPipeline::class)->run('biomass energy');

        $this->assertSame('basic', $result['tier']);
        $this->assertArrayHasKey('overview', $result);
        $this->assertArrayHasKey('knowledge_graph', $result);
        $this->assertArrayHasKey('learning_path', $result);
        $this->assertArrayHasKey('evidence', $result);
        $this->assertArrayHasKey('_meta', $result);
    }

    public function test_intermediate_tier_forced_via_config(): void
    {
        config()->set('gemini.enabled', true);
        config()->set('gemini.api_key', 'test-key');
        config()->set('gemini.force_tier', 'intermediate');
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->fakeGeminiResponse([
                'title' => 'Biomass Path',
                'overview' => [
                    'intro' => 'Biomass intro',
                    'analogy' => 'Like recycling',
                    'research_basis' => 'KU papers',
                    'expert' => 'KU expert',
                ],
                'learning_path' => [
                    'estimated_hours' => '90',
                    'subtitle' => 'Test path',
                    'phases' => [],
                ],
                'key_points' => ['point a'],
                'entities' => ['Biomass'],
                'courses' => [],
            ])),
        ]);

        $result = app(AgentPipeline::class)->run('how does biomass affect carbon');

        $this->assertSame('intermediate', $result['tier']);
        $this->assertNotEmpty($result['title']);
    }

    public function test_advanced_tier_heuristic_without_gemini_router(): void
    {
        config()->set('gemini.enabled', false);
        config()->set('gemini.force_tier', 'advanced');
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);

        $result = app(AgentPipeline::class)->run('compare carbon footprint across faculties');

        $this->assertSame('advanced', $result['tier']);
        $this->assertArrayHasKey('knowledge_graph', $result);
    }

    public function test_graph_rag_service_uses_agent_when_gemini_enabled(): void
    {
        config()->set('gemini.enabled', true);
        config()->set('gemini.api_key', 'test-key');
        config()->set('gemini.force_tier', 'basic');
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->fakeGeminiResponse([
                'title' => 'Test',
                'overview' => [
                    'intro' => 'i', 'analogy' => 'a', 'research_basis' => 'r', 'expert' => 'e',
                ],
                'learning_path' => ['phases' => []],
            ])),
        ]);

        $service = app(\App\Services\GraphRag\GraphRagService::class);
        $result = $service->search('microplastics');

        $this->assertSame('basic', $result['tier']);
    }
}
