<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Models\SearchHistory;
use App\Services\GraphRag\Agent\GeminiUsageMeter;
use App\Services\GraphRag\GraphRagService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResolveSearchResult
{
    public function __construct(
        private readonly GraphRagService $graphRag,
        private readonly GeminiUsageMeter $geminiUsage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(SearchHistory $searchHistory): array
    {
        $result = $searchHistory->result;

        if ($result === null) {
            return $this->graphRag->search($searchHistory->query);
        }

        if (($result['status'] ?? null) === 'pending') {
            return $this->completePending($searchHistory);
        }

        return $result;
    }

    /**
     * Runs the synthesis a draft deferred. The lock keeps concurrent polls of the
     * same history from each spending a Gemini call; a poll that loses the race
     * waits for the winner and then reads its result.
     *
     * @return array<string, mixed>
     */
    private function completePending(SearchHistory $searchHistory): array
    {
        $timeout = (int) config('gemini.timeout', 20) * 2;

        try {
            return Cache::lock('search-synthesis:'.$searchHistory->id, $timeout)
                ->block($timeout, function () use ($searchHistory): array {
                    $searchHistory->refresh();
                    if (($searchHistory->result['status'] ?? null) !== 'pending') {
                        return $searchHistory->result;
                    }

                    $this->geminiUsage->reset();
                    $start = microtime(true);
                    $result = $this->graphRag->search($searchHistory->query);
                    $result['status'] = 'ready';

                    $metrics = $searchHistory->metrics ?? [];
                    $metrics['gemini'] = GeminiUsageMeter::merge($metrics['gemini'] ?? null, $this->geminiUsage->snapshot());

                    $searchHistory->forceFill([
                        'result' => $result,
                        'metrics' => $metrics,
                        'synthesis_latency_ms' => (int) round((microtime(true) - $start) * 1000),
                        'query_type' => match ($result['tier'] ?? null) {
                            'advanced' => 'complex',
                            'intermediate', 'basic' => 'simple',
                            default => $searchHistory->query_type,
                        },
                    ])->save();

                    return $result;
                });
        } catch (LockTimeoutException) {
            return $searchHistory->refresh()->result;
        } catch (Throwable $e) {
            Log::warning('Deferred synthesis failed', ['search_history_id' => $searchHistory->id, 'error' => $e->getMessage()]);

            $draft = $searchHistory->result;
            $draft['status'] = 'ready';
            $searchHistory->forceFill(['result' => $draft])->save();

            return $draft;
        }
    }
}
