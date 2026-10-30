<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

/**
 * Per-request tally of Gemini usage, stored with each search so the admin
 * dashboard can report tokens, quota hits and heuristic fallbacks.
 *
 * A fallback is any enabled call that produced no usable JSON (error, quota,
 * bad output, or skipped by the open circuit): the caller then uses its
 * heuristic path.
 */
final class GeminiUsageMeter
{
    private int $calls = 0;

    private int $promptTokens = 0;

    private int $outputTokens = 0;

    private int $totalTokens = 0;

    private int $rateLimited = 0;

    private int $fallbacks = 0;

    public function reset(): void
    {
        $this->calls = 0;
        $this->promptTokens = 0;
        $this->outputTokens = 0;
        $this->totalTokens = 0;
        $this->rateLimited = 0;
        $this->fallbacks = 0;
    }

    /**
     * @param  array<string, mixed>|null  $usageMetadata
     */
    public function recordCall(?array $usageMetadata): void
    {
        $this->calls++;

        if ($usageMetadata === null) {
            return;
        }

        $prompt = (int) ($usageMetadata['promptTokenCount'] ?? 0);
        $output = (int) ($usageMetadata['candidatesTokenCount'] ?? 0) + (int) ($usageMetadata['thoughtsTokenCount'] ?? 0);

        $this->promptTokens += $prompt;
        $this->outputTokens += $output;
        $this->totalTokens += (int) ($usageMetadata['totalTokenCount'] ?? $prompt + $output);
    }

    public function recordRateLimited(): void
    {
        $this->rateLimited++;
    }

    public function recordFallback(): void
    {
        $this->fallbacks++;
    }

    /**
     * @return array{calls: int, prompt_tokens: int, output_tokens: int, total_tokens: int, rate_limited: int, fallbacks: int}
     */
    public function snapshot(): array
    {
        return [
            'calls' => $this->calls,
            'prompt_tokens' => $this->promptTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
            'rate_limited' => $this->rateLimited,
            'fallbacks' => $this->fallbacks,
        ];
    }

    /**
     * @param  array<string, int>|null  $earlier
     * @return array<string, int>
     */
    public static function merge(?array $earlier, array $later): array
    {
        $merged = $later;
        foreach ((array) $earlier as $key => $value) {
            $merged[$key] = (int) $value + (int) ($later[$key] ?? 0);
        }

        return $merged;
    }
}
