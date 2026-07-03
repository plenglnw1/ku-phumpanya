<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GraphRag\Agent\GeminiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    public function test_opens_circuit_after_rate_limit_and_skips_further_calls(): void
    {
        config()->set('gemini.enabled', true);
        config()->set('gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('quota exceeded', 429),
        ]);

        $client = app(GeminiClient::class);
        $client->resetCallCount();

        $first = $client->generateJson('gemini-2.5-flash-lite', 'test', null);
        $second = $client->generateJson('gemini-2.5-flash-lite', 'test', null);

        $this->assertNull($first);
        $this->assertNull($second);
        $this->assertTrue($client->isCircuitOpen());
        $this->assertSame(1, $client->getCallCount());
        Http::assertSentCount(1);
    }
}
