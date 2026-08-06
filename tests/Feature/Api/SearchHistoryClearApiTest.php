<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\LearningProgress;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchHistoryClearApiTest extends TestCase
{
    use RefreshDatabase;

    private function historyFor(User $user): SearchHistory
    {
        return SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => 'ไคโตซาน',
            'result' => [
                'title' => 'Chitosan',
                'learning_path' => ['phases' => [['name' => 'Phase 1', 'modules' => [['title' => 'A']]]]],
            ],
        ]);
    }

    public function test_clearing_removes_only_the_callers_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->historyFor($user);
        $this->historyFor($user);
        $strangers = $this->historyFor($other);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/search/recent')
            ->assertOk()
            ->assertJson(['deleted' => 2]);

        $this->assertDatabaseMissing('search_histories', ['user_id' => $user->id]);
        $this->assertDatabaseHas('search_histories', ['id' => $strangers->id]);
    }

    public function test_clearing_also_removes_progress_recorded_against_those_roadmaps(): void
    {
        $user = User::factory()->create();
        $history = $this->historyFor($user);

        LearningProgress::query()->create([
            'user_id' => $user->id,
            'search_history_id' => $history->id,
            'phase_index' => 0,
            'card_index' => 0,
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/search/recent')->assertOk();

        $this->assertDatabaseCount('learning_progress', 0);
    }

    public function test_clearing_an_empty_history_is_not_an_error(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/search/recent')
            ->assertOk()
            ->assertJson(['deleted' => 0]);
    }

    public function test_guests_are_rejected(): void
    {
        $this->deleteJson('/api/search/recent')->assertUnauthorized();
    }
}
