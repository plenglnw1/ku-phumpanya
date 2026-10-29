<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GraphRag\Agent\GeminiClient;
use Illuminate\Http\Client\Request;
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

    public function test_sends_the_key_as_a_header_and_turns_thinking_off(): void
    {
        config()->set('gemini.enabled', true);
        config()->set('gemini.api_key', 'test-key');
        config()->set('gemini.thinking_budget', 0);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]],
            ]),
        ]);

        $result = app(GeminiClient::class)->generateJson('gemini-2.5-flash', 'test', null);

        $this->assertSame(['ok' => true], $result);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'test-key')
            && ! str_contains($request->url(), 'test-key')
            && $request['generationConfig']['thinkingConfig'] === ['thinkingBudget' => 0]);
    }
}
