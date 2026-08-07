<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_password_login_returns_user(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $response = $this
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('role', 'admin');

        $this->assertAuthenticated();
    }

    public function test_non_admin_cannot_access_admin_stats(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/admin/stats')
            ->assertForbidden();
    }

    public function test_admin_can_fetch_stats_and_logs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'faculty' => 'วนศาสตร์',
        ]);

        $student = User::factory()->create([
            'role' => UserRole::Student,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'faculty' => 'เกษตรศาสตร์',
        ]);

        SearchHistory::query()->create([
            'user_id' => $student->id,
            'query' => 'Carbon Credit',
            'status' => 'success',
            'query_type' => 'simple',
            'role_snapshot' => 'student',
            'faculty_snapshot' => 'เกษตรศาสตร์',
            'total_latency_ms' => 250,
            'total_nodes_found' => 3,
            'metrics' => [
                'top_node_labels' => ['Carbon Credit', 'BCG'],
            ],
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/stats?period=7d')
            ->assertOk()
            ->assertJsonStructure([
                'total_searches' => ['value', 'delta_pct'],
                'active_users' => ['value', 'delta_pct'],
                'avg_latency_ms' => ['value', 'delta_pct'],
                'zero_result_rate' => ['value', 'delta_pct'],
            ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/analytics?period=7d')
            ->assertOk()
            ->assertJsonStructure([
                'search_volume_trend',
                'top_topics',
                'role_breakdown',
                'top_nodes',
            ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/search-logs?period=7d')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $faculties = config('ku_faculties.faculties', []);
        $faculty = $faculties[0] ?? 'วนศาสตร์';

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'password' => 'password',
                'role' => 'admin',
                'faculty' => $faculty,
                'department' => 'ระบบสารสนเทศ',
                'employee_id' => '90000001',
            ])
            ->assertCreated()
            ->assertJsonPath('email', 'new-admin@example.com')
            ->assertJsonPath('role', 'admin');

        $this->assertDatabaseHas('users', [
            'email' => 'new-admin@example.com',
            'role' => 'admin',
        ]);
    }
}
