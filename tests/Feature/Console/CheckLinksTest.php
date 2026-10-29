<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('elasticsearch.enabled', false);
        config()->set('graphrag_seed.topics', [[
            'sources' => [['source' => 'KUKR', 'url' => 'https://kukr.test/ok']],
            'courses' => [['url' => 'https://mooc.test/ok']],
        ]]);
        Storage::fake('local');
    }

    public function test_passes_when_every_link_answers_200(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $this->artisan('links:check', ['--delay' => 0])->assertExitCode(0);

        $report = json_decode(Storage::disk('local')->get(Storage::disk('local')->files('reports')[0]), true);
        $this->assertSame(1, $report['summary']['KUKR']['ok']);
        $this->assertSame(1, $report['summary']['KU_MOOC']['ok']);
        $this->assertSame([], $report['failures']);
    }

    public function test_reports_failures_from_stored_evidence(): void
    {
        SearchHistory::query()->create([
            'user_id' => User::factory()->create()->id,
            'query' => 'q',
            'result' => ['evidence' => [['source' => 'KU_Forest', 'url' => 'https://forest.test/gone']]],
        ]);
        Http::fake([
            'forest.test/*' => Http::response('', 404),
            '*' => Http::response('ok'),
        ]);

        $this->artisan('links:check', ['--delay' => 0])
            ->expectsOutputToContain('404 https://forest.test/gone')
            ->assertExitCode(1);
    }
}
