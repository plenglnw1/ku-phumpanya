<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PdpaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_payload_hides_google_id_and_asks_for_consent(): void
    {
        Sanctum::actingAs(User::factory()->create(['google_id' => 'g-123']));

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonMissingPath('google_id')
            ->assertJsonMissingPath('password')
            ->assertJsonPath('consent_required', true);
    }

    public function test_consent_is_recorded_with_the_current_version(): void
    {
        config()->set('pdpa.consent_version', '2026-10');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/consent')->assertOk()->assertJsonPath('consent_version', '2026-10');
        $this->getJson('/api/user')->assertJsonPath('consent_required', false);

        config()->set('pdpa.consent_version', '2027-01');
        $this->getJson('/api/user')->assertJsonPath('consent_required', true);
    }

    public function test_google_account_deletes_itself_by_confirming_its_email(): void
    {
        $user = User::factory()->create(['email' => 'nisit@ku.th', 'password' => null, 'google_id' => 'g-1']);
        SearchHistory::query()->create(['user_id' => $user->id, 'query' => 'q']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/profile', ['confirm_email' => 'someone@ku.th'])->assertUnprocessable();
        $this->deleteJson('/api/profile', ['confirm_email' => ' Nisit@KU.th '])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('search_histories', ['user_id' => $user->id]);
    }
}
