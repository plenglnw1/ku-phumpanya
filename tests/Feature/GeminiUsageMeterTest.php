<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GraphRag\Agent\GeminiClient;
use App\Services\GraphRag\Agent\GeminiUsageMeter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiUsageMeterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['gemini.enabled' => true, 'gemini.api_key' => 'test-key']);
    }

    public function test_successful_call_records_tokens(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 20, 'thoughtsTokenCount' => 5, 'totalTokenCount' => 125],
        ])]);

        $this->assertSame(['ok' => true], app(GeminiClient::class)->generateJson('gemini-test', 'prompt'));

        $this->assertSame([
            'calls' => 1,
            'prompt_tokens' => 100,
            'output_tokens' => 25,
            'total_tokens' => 125,
            'rate_limited' => 0,
            'fallbacks' => 0,
        ], app(GeminiUsageMeter::class)->snapshot());
    }

    public function test_quota_error_counts_rate_limit_and_fallbacks_for_skipped_calls(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 429]], 429)]);

        $client = app(GeminiClient::class);
        $this->assertNull($client->generateJson('gemini-test', 'first'));
        $this->assertNull($client->generateJson('gemini-test', 'skipped by open circuit'));

        $usage = app(GeminiUsageMeter::class)->snapshot();
        $this->assertSame(1, $usage['calls']);
        $this->assertSame(1, $usage['rate_limited']);
        $this->assertSame(2, $usage['fallbacks']);
        Http::assertSentCount(1);
    }

    public function test_merge_adds_draft_and_deferred_usage(): void
    {
        $this->assertSame(
            ['calls' => 3, 'total_tokens' => 50],
            GeminiUsageMeter::merge(['calls' => 1, 'total_tokens' => 10], ['calls' => 2, 'total_tokens' => 40]),
        );
    }
}
