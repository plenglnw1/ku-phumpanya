<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\SearchHistories\SearchHistoryResource;
use App\Filament\Widgets\Business\BusinessStatsOverview;
use App\Filament\Widgets\Business\NewUsersChart;
use App\Filament\Widgets\Business\SearchesByFacultyChart;
use App\Filament\Widgets\Business\SearchesByRoleChart;
use App\Filament\Widgets\Business\SearchesPerDayChart;
use App\Filament\Widgets\Business\TopConceptsChart;
use App\Filament\Widgets\Business\TopQueriesTable;
use App\Filament\Widgets\System\GeminiTokensChart;
use App\Filament\Widgets\System\IndexHealthOverview;
use App\Filament\Widgets\System\LatencyChart;
use App\Filament\Widgets\System\RecentErrorsTable;
use App\Filament\Widgets\System\SystemStatsOverview;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = User::factory()->create(['role' => UserRole::Student]);

        SearchHistory::create([
            'user_id' => $student->id, 'query' => 'คาร์บอน', 'status' => 'success',
            'role_snapshot' => 'student', 'faculty_snapshot' => 'วนศาสตร์', 'total_latency_ms' => 1800,
            'metrics' => ['top_node_labels' => ['ลดคาร์บอน'], 'gemini' => ['calls' => 2, 'total_tokens' => 900, 'rate_limited' => 0, 'fallbacks' => 0]],
        ]);
        SearchHistory::create([
            'user_id' => $student->id, 'query' => 'ล่ม', 'status' => 'error',
            'role_snapshot' => 'student', 'metrics' => ['error_message' => 'Gemini timeout'],
        ]);
    }

    public function test_business_dashboard_is_the_panel_home(): void
    {
        $this->actingAs($this->admin)
            ->get('/filament')
            ->assertOk()
            ->assertSee('Business dashboard')
            ->assertDontSee('Views');
    }

    public function test_system_page_is_reachable(): void
    {
        $this->actingAs($this->admin)
            ->get(SystemHealth::getUrl())
            ->assertOk()
            ->assertSee('System health');
    }

    /**
     * @param  class-string  $widget
     */
    #[DataProvider('widgets')]
    public function test_widget_renders(string $widget, string $expected): void
    {
        Livewire::actingAs($this->admin)
            ->test($widget)
            ->assertOk()
            ->assertSee($expected);
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function widgets(): array
    {
        return [
            'business stats' => [BusinessStatsOverview::class, 'ผู้ใช้ที่ใช้งาน 7 วัน'],
            'searches per day' => [SearchesPerDayChart::class, 'การค้นหาและผู้ใช้ต่อวัน'],
            'new users' => [NewUsersChart::class, 'ผู้ใช้ใหม่ต่อสัปดาห์'],
            'by role' => [SearchesByRoleChart::class, 'การค้นหาตามบทบาท'],
            'by faculty' => [SearchesByFacultyChart::class, 'การค้นหาตามคณะ'],
            'concepts' => [TopConceptsChart::class, 'หัวข้อที่ผู้ใช้สนใจ'],
            'top queries' => [TopQueriesTable::class, 'คาร์บอน'],
            'system stats' => [SystemStatsOverview::class, 'Latency p50 / p95'],
            'index health' => [IndexHealthOverview::class, 'ปิดอยู่'],
            'latency chart' => [LatencyChart::class, 'Latency รวมต่อวัน'],
            'gemini chart' => [GeminiTokensChart::class, 'Gemini tokens ต่อวัน'],
            'recent errors' => [RecentErrorsTable::class, 'Gemini timeout'],
        ];
    }

    public function test_search_history_and_activity_log_are_read_only(): void
    {
        $this->assertFalse(SearchHistoryResource::canCreate());
        $this->assertFalse(SearchHistoryResource::canEdit(SearchHistory::first()));
        $this->assertFalse(SearchHistoryResource::canDelete(SearchHistory::first()));
        $this->assertFalse(ActivityLogResource::canCreate());
        $this->assertSame(['index', 'view'], array_keys(SearchHistoryResource::getPages()));
        $this->assertSame(['index', 'view'], array_keys(ActivityLogResource::getPages()));

        $this->actingAs($this->admin)
            ->get(SearchHistoryResource::getUrl('index'))
            ->assertOk()
            ->assertSee('ดูกราฟ');
    }
}
