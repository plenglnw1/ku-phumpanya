<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gemini.enabled', false);
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);
    }

    public function test_learning_without_history_id_uses_default_query(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/learning')
            ->assertOk()
            ->assertJsonStructure([
                'search_history_id',
                'result' => ['title', 'overview', 'knowledge_graph', 'learning_path', 'evidence'],
            ])
            ->assertJsonPath('search_history_id', null);
    }

    public function test_learning_with_history_id_returns_owner_result(): void
    {
        $user = User::factory()->create();
        $history = SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => 'biomass energy',
            'result' => [
                'title' => 'Biomass',
                'overview' => ['intro' => 'x', 'analogy' => '', 'research_basis' => '', 'expert' => ''],
                'knowledge_graph' => ['nodes' => [], 'edges' => []],
                'learning_path' => ['phases' => []],
                'evidence' => [],
            ],
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/learning?search_history_id={$history->id}")
            ->assertOk()
            ->assertJsonPath('search_history_id', $history->id)
            ->assertJsonPath('result.title', 'Biomass');
    }

    public function test_learning_forbidden_for_other_users_history(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $history = SearchHistory::query()->create([
            'user_id' => $owner->id,
            'query' => 'private',
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/learning?search_history_id={$history->id}")
            ->assertForbidden();
    }
}
