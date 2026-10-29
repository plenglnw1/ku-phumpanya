<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchAsyncSynthesisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gemini.enabled', true);
        config()->set('gemini.api_key', 'test-key');
        config()->set('gemini.force_tier', 'basic');
        config()->set('gemini.async_synthesis', true);
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'title' => 'Synthesized title',
                    'overview' => ['intro' => 'i', 'analogy' => 'a', 'research_basis' => 'r', 'expert' => 'e'],
                    'learning_path' => ['estimated_hours' => '10', 'subtitle' => 's', 'phases' => []],
                ])]]]]],
            ]),
        ]);
    }

    public function test_post_returns_a_pending_draft_without_calling_gemini(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/search', ['query' => 'biomass energy'])
            ->assertCreated()
            ->assertJsonPath('data.result.status', 'pending')
            ->assertJsonStructure(['data' => ['result' => ['title', 'overview', 'knowledge_graph', 'learning_path', 'evidence', 'tags']]]);

        Http::assertNothingSent();
    }

    public function test_get_completes_the_synthesis_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/search', ['query' => 'biomass energy'])->json('data.id');

        $this->getJson("/api/search/history/{$id}")
            ->assertOk()
            ->assertJsonPath('data.result.status', 'ready')
            ->assertJsonPath('data.result.title', 'Synthesized title');

        $sent = count(Http::recorded());
        $this->getJson("/api/search/history/{$id}")->assertJsonPath('data.result.status', 'ready');

        $this->assertCount($sent, Http::recorded());
        $this->assertSame('ready', SearchHistory::query()->find($id)->result['status']);
    }
}
