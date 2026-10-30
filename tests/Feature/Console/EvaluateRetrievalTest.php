<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Admin\SystemMetrics;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvaluateRetrievalTest extends TestCase
{
    public function test_scores_topics_and_saves_the_report(): void
    {
        Storage::fake('local');

        $this->artisan('eval:retrieval')
            ->expectsOutputToContain('Mean Recall@10: 50.0%')
            ->assertSuccessful();

        $report = json_decode(Storage::disk('local')->get(SystemMetrics::RECALL_REPORT), true);
        $this->assertSame(10, $report['k']);
        $this->assertSame(0.5, $report['recall']);
        $this->assertCount(6, $report['topics']);
        $this->assertSame(['mode' => 'urls', 'hits' => 3, 'recall' => 1], array_intersect_key($report['topics']['4'], array_flip(['mode', 'hits', 'recall'])));
        $this->assertSame('topic_id', $report['topics']['1']['mode']);
        $this->assertSame(0.5, app(SystemMetrics::class)->recall()['recall']);
    }

    public function test_dry_run_does_not_save(): void
    {
        Storage::fake('local');

        $this->artisan('eval:retrieval', ['--dry-run' => true, '--k' => 5])
            ->expectsOutputToContain('Mean Recall@5')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing(SystemMetrics::RECALL_REPORT);
    }
}
