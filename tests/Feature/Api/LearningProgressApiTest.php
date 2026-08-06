<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\LearningProgress;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningProgressApiTest extends TestCase
{
    use RefreshDatabase;

    /** A stored roadmap with two phases holding three modules in total. */
    private function roadmapFor(User $user): SearchHistory
    {
        return SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => 'microplastics water quality',
            'result' => [
                'title' => 'Microplastics',
                'learning_path' => [
                    'estimated_hours' => '48-72',
                    'phases' => [
                        ['name' => 'Phase 1', 'modules' => [['title' => 'A'], ['title' => 'B']]],
                        ['name' => 'Phase 2', 'modules' => [['title' => 'C']]],
                    ],
                ],
            ],
        ]);
    }

    public function test_progress_starts_empty_and_counts_stored_modules(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $history = $this->roadmapFor($user);

        $this->getJson("/api/search/history/{$history->id}/progress")
            ->assertOk()
            ->assertJson(['data' => [
                'completed' => [],
                'completed_count' => 0,
                'total' => 3,
                'percent' => 0,
            ]]);
    }

    public function test_completing_and_uncompleting_a_module_moves_the_percentage(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $history = $this->roadmapFor($user);

        $this->putJson("/api/search/history/{$history->id}/progress", [
            'phase' => 0, 'card' => 1, 'completed' => true,
        ])->assertOk()->assertJson(['data' => [
            'completed' => [['phase' => 0, 'card' => 1]],
            'completed_count' => 1,
            'total' => 3,
            'percent' => 33,
        ]]);

        $this->assertDatabaseCount('learning_progress', 1);

        // Repeating the same completion must not double-count it.
        $this->putJson("/api/search/history/{$history->id}/progress", [
            'phase' => 0, 'card' => 1, 'completed' => true,
        ])->assertOk()->assertJsonPath('data.completed_count', 1);

        $this->assertDatabaseCount('learning_progress', 1);

        $this->putJson("/api/search/history/{$history->id}/progress", [
            'phase' => 0, 'card' => 1, 'completed' => false,
        ])->assertOk()->assertJson(['data' => [
            'completed' => [],
            'completed_count' => 0,
            'percent' => 0,
        ]]);

        $this->assertDatabaseCount('learning_progress', 0);
    }

    public function test_module_outside_the_stored_roadmap_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $history = $this->roadmapFor($user);

        $this->putJson("/api/search/history/{$history->id}/progress", [
            'phase' => 1, 'card' => 5, 'completed' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('card');

        $this->assertDatabaseCount('learning_progress', 0);
    }

    public function test_a_completion_left_over_from_a_larger_roadmap_is_not_counted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $history = $this->roadmapFor($user);

        LearningProgress::query()->create([
            'user_id' => $user->id,
            'search_history_id' => $history->id,
            'phase_index' => 4,
            'card_index' => 0,
            'completed_at' => now(),
        ]);

        $this->getJson("/api/search/history/{$history->id}/progress")
            ->assertOk()
            ->assertJson(['data' => ['completed' => [], 'completed_count' => 0, 'percent' => 0]]);
    }

    public function test_another_users_roadmap_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $history = $this->roadmapFor($owner);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/search/history/{$history->id}/progress")->assertForbidden();

        $this->putJson("/api/search/history/{$history->id}/progress", [
            'phase' => 0, 'card' => 0, 'completed' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('learning_progress', 0);
    }

    public function test_guests_are_rejected(): void
    {
        $history = $this->roadmapFor(User::factory()->create());

        $this->getJson("/api/search/history/{$history->id}/progress")->assertUnauthorized();
    }
}
