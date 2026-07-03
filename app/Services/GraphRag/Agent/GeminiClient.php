<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GeminiClient
{
    private int $callCount = 0;

    /** Skip further API calls this request after quota / overload errors. */
    private bool $circuitOpen = false;

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function isCircuitOpen(): bool
    {
        return $this->circuitOpen;
    }

    public function resetCallCount(): void
    {
        $this->callCount = 0;
        $this->circuitOpen = false;
    }

    /**
     * @param  array<string, mixed>|null  $responseSchema
     * @return array<string, mixed>|null
     */
    public function generateJson(string $model, string $prompt, ?array $responseSchema = null): ?array
    {
        if ($this->circuitOpen || ! config('gemini.enabled') || config('gemini.api_key') === '') {
            return null;
        }

        $url = sprintf(
            '%s/models/%s:generateContent?key=%s',
            rtrim((string) config('gemini.base_url'), '/'),
            $model,
            config('gemini.api_key'),
        );

        $generationConfig = [
            'temperature' => (float) config('gemini.temperature', 0.3),
            'maxOutputTokens' => (int) config('gemini.max_output_tokens', 2048),
            'responseMimeType' => 'application/json',
        ];

        if ($responseSchema !== null) {
            $generationConfig['responseSchema'] = $responseSchema;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout((int) config('gemini.timeout', 20))
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => $generationConfig,
                ]);
        } catch (\Throwable $e) {
            Log::warning('GeminiClient: request failed', ['error' => $e->getMessage()]);

            return null;
        }

        $this->callCount++;

        if (! $response->successful()) {
            $status = $response->status();
            if (in_array($status, [429, 503], true)) {
                $this->circuitOpen = true;
            }

            Log::warning('GeminiClient: non-200', ['status' => $status, 'body' => $response->body()]);

            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
