<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchMockUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_search_home(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student]);

        $this->actingAs($user)
            ->get(route('search.index'))
            ->assertOk()
            ->assertSee('Start your next discovery');
    }

    public function test_search_creates_history_and_shows_results(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student]);
        config()->set('elasticsearch.enabled', false);

        $response = $this->actingAs($user)->post(route('search.store'), [
            'query' => 'Impact of AI in Medicine',
        ]);

        $history = SearchHistory::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($history);

        $response->assertRedirect(route('search.show', $history));

        $this->actingAs($user)
            ->get(route('search.show', ['searchHistory' => $history, 'tab' => 'graph']))
            ->assertOk()
            ->assertSee('Knowledge Graph')
            ->assertSee('Researcher connections')
            ->assertSee('Node types');

        $this->actingAs($user)
            ->get(route('search.show', ['searchHistory' => $history, 'tab' => 'overview']))
            ->assertOk()
            ->assertSee('Evidence from KU sources')
            ->assertSee('GraphRAG result');
    }

    public function test_user_cannot_view_another_users_search_history(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Student]);
        $other = User::factory()->create(['role' => UserRole::Student]);

        $history = SearchHistory::query()->create([
            'user_id' => $owner->id,
            'query' => 'Private query',
        ]);

        $this->actingAs($other)
            ->get(route('search.show', $history))
            ->assertForbidden();
    }

    public function test_learning_and_smart_picks_pages_load(): void
    {
        $user = User::factory()->create(['role' => UserRole::Researcher]);

        $this->actingAs($user)->get(route('learning.show'))->assertOk()->assertSee('My Progress');
        $this->actingAs($user)->get(route('smart-picks.index'))->assertOk()->assertSee('AI Recommendations');
    }
}
