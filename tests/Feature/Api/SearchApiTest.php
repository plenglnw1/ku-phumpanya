<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gemini.enabled', false);
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);
    }

    public function test_post_search_returns_201_with_spec_shape(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/search', [
            'query' => 'carbon footprint in agriculture',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'query',
                    'result' => [
                        'title',
                        'overview',
                        'knowledge_graph',
                        'learning_path',
                        'evidence',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.query', 'carbon footprint in agriculture');

        $this->assertDatabaseHas('search_histories', [
            'user_id' => $user->id,
            'query' => 'carbon footprint in agriculture',
        ]);
    }

    public function test_post_search_requires_query(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/search', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['query']]);
    }

    public function test_get_search_history_returns_owner_result(): void
    {
        $user = User::factory()->create();
        $history = SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => 'microplastics',
            'result' => [
                'title' => 'Microplastics',
                'overview' => ['intro' => 'test', 'analogy' => '', 'research_basis' => '', 'expert' => ''],
                'knowledge_graph' => ['center' => [], 'nodes' => [], 'edges' => []],
                'learning_path' => ['phases' => []],
                'evidence' => [],
            ],
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/search/history/{$history->id}?tab=graph")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'query', 'tab', 'result', 'created_at'],
            ])
            ->assertJsonPath('data.tab', 'graph')
            ->assertJsonPath('data.query', 'microplastics');
    }

    public function test_get_search_history_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $history = SearchHistory::query()->create([
            'user_id' => $owner->id,
            'query' => 'private',
            'result' => null,
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/search/history/{$history->id}")
            ->assertForbidden();
    }

    public function test_get_recent_returns_max_ten(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            SearchHistory::query()->create([
                'user_id' => $user->id,
                'query' => "query {$i}",
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/search/recent');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'query', 'created_at'],
                ],
            ]);
    }

    public function test_get_suggestions_returns_list(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/search/suggestions')
            ->assertOk()
            ->assertJsonStructure(['suggestions']);
    }

    public function test_incomplete_profile_cannot_search(): void
    {
        Sanctum::actingAs(User::factory()->profileIncomplete()->create());

        $this->postJson('/api/search', ['query' => 'test'])
            ->assertForbidden();
    }
}
