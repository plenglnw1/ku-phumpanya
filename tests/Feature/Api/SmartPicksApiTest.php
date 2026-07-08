<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartPicksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_smart_picks_returns_spec_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/smart-picks')
            ->assertOk()
            ->assertJsonStructure([
                'smart_picks' => [
                    'headline',
                    'items' => [
                        '*' => ['title', 'description', 'query', 'badge'],
                    ],
                ],
            ])
            ->assertJsonPath('smart_picks.headline', 'AI Recommendations');
    }
}
