<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchLogExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_downloads_csv_with_formulas_neutralised(): void
    {
        $student = User::factory()->create();
        SearchHistory::query()->create(['user_id' => $student->id, 'query' => 'ไคโตซาน', 'role_snapshot' => 'student']);
        SearchHistory::query()->create(['user_id' => $student->id, 'query' => '=HYPERLINK("x")', 'role_snapshot' => 'student']);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $response = $this->get('/api/admin/search-logs/export');

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith('timestamp,user_role,faculty,raw_query', $csv);
        $this->assertStringContainsString('ไคโตซาน', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_non_admin_cannot_export(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/search-logs/export')->assertForbidden();
    }
}
