<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\LearningProgress;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\Admin\BusinessMetrics;
use App\Services\Admin\SystemMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentile_uses_nearest_rank(): void
    {
        $this->assertNull(SystemMetrics::percentile([], 50));
        $this->assertSame(300, SystemMetrics::percentile([500, 100, 300, 200, 400], 50));
        $this->assertSame(1000, SystemMetrics::percentile([...array_fill(0, 19, 100), 1000], 96));
        $this->assertSame(100, SystemMetrics::percentile([...array_fill(0, 19, 100), 1000], 95));
    }

    public function test_business_summary_excludes_admins_and_counts_engagement(): void
    {
        config(['pdpa.consent_version' => 'v1']);
        $student = User::factory()->create(['role' => UserRole::Student, 'faculty' => 'วนศาสตร์', 'consented_at' => now(), 'consent_version' => 'v1']);
        $teacher = User::factory()->create(['role' => UserRole::Teacher, 'faculty' => null, 'consented_at' => null]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $answered = $this->search($student, 'คาร์บอน', 'success', ['top_node_labels' => ['ลดคาร์บอน', 'ป่าไม้', 'วนศาสตร์']]);
        $this->search($student, ' คาร์บอน ', 'success', ['top_node_labels' => ['ลดคาร์บอน']]);
        $this->search($teacher, 'xyz', 'zero_results');
        $this->search($admin, 'admin test', 'success');

        LearningProgress::create([
            'user_id' => $student->id, 'search_history_id' => $answered->id,
            'phase_index' => 0, 'card_index' => 0, 'completed_at' => now(),
        ]);

        $metrics = app(BusinessMetrics::class);
        $summary = $metrics->summary();

        $this->assertSame(2, $summary['active_7d']);
        $this->assertSame(3, $summary['searches_30d']);
        $this->assertSame(33.3, $summary['zero_result_rate_30d']);
        $this->assertSame(50.0, $summary['consent_pct']);
        $this->assertSame(50.0, $summary['learning_started_pct_30d']);
        $this->assertSame(1, $summary['cards_completed_30d']);
        $this->assertSame(2, $summary['users_total']);

        $this->assertSame(['query' => 'คาร์บอน', 'count' => 2, 'zero_results' => 0], $metrics->topQueries()[0]);
        $this->assertSame(['ลดคาร์บอน' => 2, 'ป่าไม้' => 1], $metrics->topConcepts());
        $this->assertSame(['วนศาสตร์' => 2, 'ไม่ระบุคณะ' => 1], $metrics->searchesByFaculty());
        $this->assertSame(3, collect($metrics->searchesPerDay(7))->sum('searches'));
    }

    public function test_system_metrics_report_latency_errors_and_gemini(): void
    {
        $user = User::factory()->create();
        foreach ([1000, 2000, 3000, 4000] as $ms) {
            $this->search($user, 'q', 'success', ['gemini' => ['calls' => 2, 'total_tokens' => 500, 'rate_limited' => 0, 'fallbacks' => 0]], $ms);
        }
        $this->search($user, 'broken', 'error', ['error_message' => 'ES down', 'gemini' => ['calls' => 1, 'total_tokens' => 0, 'rate_limited' => 1, 'fallbacks' => 1]]);
        $this->search($user, 'old row', 'success');
        $this->search($user, 'unmeasured', 'success', [], 0);

        $system = app(SystemMetrics::class);

        $latency = $system->latency();
        $this->assertSame(2000, $latency['total']['p50']);
        $this->assertSame(4000, $latency['total']['p95']);

        $errors = $system->errors();
        $this->assertSame(14.3, $errors['rate']);
        $this->assertSame('ES down', $errors['recent'][0]['message']);

        $gemini = $system->gemini();
        $this->assertSame(4, $latency['total']['samples']);
        $this->assertSame(5, $gemini['searches']);
        $this->assertSame(9, $gemini['calls']);
        $this->assertSame(2000, $gemini['total_tokens']);
        $this->assertSame(1, $gemini['rate_limited']);
        $this->assertSame(1, $gemini['fallbacks']);
    }

    public function test_reports_are_read_from_local_storage(): void
    {
        Storage::fake('local');
        $system = app(SystemMetrics::class);
        $this->assertNull($system->linkCheck());
        $this->assertNull($system->recall());
        $this->assertFalse($system->elasticsearch()['enabled']);

        Storage::disk('local')->put('reports/links-20261001-000000.json', json_encode(['checked_at' => 'old', 'summary' => ['KUKR' => ['checked' => 1, 'ok' => 0]]]));
        Storage::disk('local')->put('reports/links-20261029-000000.json', json_encode(['checked_at' => 'new', 'summary' => ['KUKR' => ['checked' => 3, 'ok' => 2], 'KU_MOOC' => ['checked' => 1, 'ok' => 1]]]));
        Storage::disk('local')->put(SystemMetrics::RECALL_REPORT, json_encode(['ran_at' => 'today', 'k' => 10, 'recall' => 0.8, 'topics' => []]));

        $links = $system->linkCheck();
        $this->assertSame('new', $links['checked_at']);
        $this->assertSame(75.0, $links['percent']);
        $this->assertSame(0.8, $system->recall()['recall']);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function search(User $user, string $query, string $status, array $metrics = [], ?int $latency = null): SearchHistory
    {
        return SearchHistory::create([
            'user_id' => $user->id,
            'query' => $query,
            'status' => $status,
            'role_snapshot' => $user->role?->value,
            'faculty_snapshot' => $user->faculty,
            'total_latency_ms' => $latency,
            'metrics' => $metrics,
        ]);
    }
}
