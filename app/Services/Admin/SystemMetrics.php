<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\SearchHistory;
use App\Services\GraphRag\ElasticClientFactory;
use Carbon\CarbonImmutable;
use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operational numbers for the admin System page: latency percentiles, errors,
 * Gemini usage, index health, link health and the last offline Recall@10 run.
 */
final class SystemMetrics
{
    public const RECALL_REPORT = 'reports/retrieval-eval.json';

    private const ES_CACHE_SECONDS = 300;

    private const LATENCY_COLUMNS = [
        'total' => 'total_latency_ms',
        'retrieval' => 'retrieval_latency_ms',
        'synthesis' => 'synthesis_latency_ms',
    ];

    public function __construct(
        private readonly ElasticClientFactory $factory,
    ) {}

    /**
     * Zero timings mean the stage was not measured; counting them drags the median to 0.
     *
     * @return array<string, array{p50: ?int, p95: ?int, samples: int}>
     */
    public function latency(int $days = 7): array
    {
        $rows = $this->recent($days)
            ->where('status', '!=', 'error')
            ->get(array_values(self::LATENCY_COLUMNS));

        $stats = [];
        foreach (self::LATENCY_COLUMNS as $name => $column) {
            $values = $rows->pluck($column)->filter(fn ($v): bool => (int) $v > 0)->map(fn ($v): int => (int) $v)->values()->all();
            $stats[$name] = [
                'p50' => self::percentile($values, 50),
                'p95' => self::percentile($values, 95),
                'samples' => count($values),
            ];
        }

        return $stats;
    }

    /**
     * Daily p50/p95 of total latency, oldest day first.
     *
     * @return list<array{date: string, p50: ?int, p95: ?int}>
     */
    public function latencyPerDay(int $days = 14): array
    {
        $byDay = $this->recent($days)
            ->where('status', '!=', 'error')
            ->where('total_latency_ms', '>', 0)
            ->get(['total_latency_ms', 'created_at'])
            ->groupBy(fn (SearchHistory $h): string => $h->created_at->toDateString());

        return $this->days($days)->map(function (string $date) use ($byDay): array {
            $values = $byDay->get($date, collect())->pluck('total_latency_ms')->map(fn ($v): int => (int) $v)->all();

            return ['date' => $date, 'p50' => self::percentile($values, 50), 'p95' => self::percentile($values, 95)];
        })->all();
    }

    /**
     * @return array{total: int, errors: int, rate: float, recent: list<array{id: int, query: string, message: ?string, at: string}>}
     */
    public function errors(int $days = 7): array
    {
        $total = $this->recent($days)->count();
        $errors = $this->recent($days)->where('status', 'error')->count();

        $recent = SearchHistory::query()
            ->where('status', 'error')
            ->latest()
            ->limit(10)
            ->get(['id', 'query', 'metrics', 'created_at'])
            ->map(fn (SearchHistory $h): array => [
                'id' => $h->id,
                'query' => Str::limit($h->query, 80),
                'message' => isset($h->metrics['error_message']) ? Str::limit((string) $h->metrics['error_message'], 160) : null,
                'at' => $h->created_at->toDateTimeString(),
            ])
            ->all();

        return [
            'total' => $total,
            'errors' => $errors,
            'rate' => $total > 0 ? round($errors / $total * 100, 1) : 0.0,
            'recent' => $recent,
        ];
    }

    /**
     * Gemini usage summed over searches that recorded it (older rows predate the meter).
     *
     * @return array{searches: int, calls: int, total_tokens: int, avg_tokens: ?int, rate_limited: int, fallbacks: int, per_day: list<array{date: string, tokens: int}>}
     */
    public function gemini(int $days = 7): array
    {
        $rows = $this->recent($days)
            ->get(['metrics', 'created_at'])
            ->filter(fn (SearchHistory $h): bool => is_array($h->metrics['gemini'] ?? null));

        $sum = fn (string $key): int => (int) $rows->sum(fn (SearchHistory $h): int => (int) ($h->metrics['gemini'][$key] ?? 0));
        $tokens = $sum('total_tokens');

        $byDay = $rows->groupBy(fn (SearchHistory $h): string => $h->created_at->toDateString());

        return [
            'searches' => $rows->count(),
            'calls' => $sum('calls'),
            'total_tokens' => $tokens,
            'avg_tokens' => $rows->isEmpty() ? null : (int) round($tokens / $rows->count()),
            'rate_limited' => $sum('rate_limited'),
            'fallbacks' => $sum('fallbacks'),
            'per_day' => $this->days($days)->map(fn (string $date): array => [
                'date' => $date,
                'tokens' => (int) $byDay->get($date, collect())->sum(fn (SearchHistory $h): int => (int) ($h->metrics['gemini']['total_tokens'] ?? 0)),
            ])->all(),
        ];
    }

    /**
     * @return array{enabled: bool, error: ?string, health: ?string, indices: array<string, array{name: string, docs: int, size_bytes: int, created_at: ?string}>, sources: array<string, int>}
     */
    public function elasticsearch(): array
    {
        return Cache::remember('admin:system:es', self::ES_CACHE_SECONDS, fn (): array => $this->readElasticsearch());
    }

    /**
     * Latest `links:check` report, if one has been written on this server.
     *
     * @return array{checked_at: string, checked: int, ok: int, percent: float, sources: array<string, array<string, mixed>>}|null
     */
    public function linkCheck(): ?array
    {
        $disk = Storage::disk('local');
        $latest = collect($disk->files('reports'))
            ->filter(fn (string $path): bool => Str::is('reports/links-*.json', $path))
            ->sort()
            ->last();

        $report = $latest === null ? null : json_decode((string) $disk->get($latest), true);
        if (! is_array($report) || ! is_array($report['summary'] ?? null)) {
            return null;
        }

        $checked = (int) collect($report['summary'])->sum('checked');
        $ok = (int) collect($report['summary'])->sum('ok');

        return [
            'checked_at' => (string) ($report['checked_at'] ?? ''),
            'checked' => $checked,
            'ok' => $ok,
            'percent' => $checked > 0 ? round($ok / $checked * 100, 1) : 0.0,
            'sources' => $report['summary'],
        ];
    }

    /**
     * Last `eval:retrieval` run.
     *
     * @return array{ran_at: string, k: int, recall: float, topics: array<string, mixed>}|null
     */
    public function recall(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::RECALL_REPORT)) {
            return null;
        }

        $report = json_decode((string) $disk->get(self::RECALL_REPORT), true);

        return is_array($report) && isset($report['recall']) ? $report : null;
    }

    /**
     * Nearest-rank percentile.
     *
     * @param  list<int>  $values
     */
    public static function percentile(array $values, int $percent): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $rank = (int) ceil($percent / 100 * count($values));

        return $values[max(0, $rank - 1)];
    }

    /**
     * @return Builder<SearchHistory>
     */
    private function recent(int $days): Builder
    {
        return SearchHistory::query()->where('created_at', '>=', CarbonImmutable::now()->subDays($days - 1)->startOfDay());
    }

    /**
     * @return Collection<int, string>
     */
    private function days(int $days): Collection
    {
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        return collect(range(0, $days - 1))->map(fn (int $i): string => $start->addDays($i)->toDateString());
    }

    /**
     * @return array{enabled: bool, error: ?string, health: ?string, indices: array<string, array{name: string, docs: int, size_bytes: int, created_at: ?string}>, sources: array<string, int>}
     */
    private function readElasticsearch(): array
    {
        $empty = ['enabled' => false, 'error' => null, 'health' => null, 'indices' => [], 'sources' => []];

        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return $empty;
        }

        $names = array_filter([
            'docs' => (string) config('elasticsearch.indices.docs'),
            'relations' => (string) config('elasticsearch.indices.relations'),
        ]);

        try {
            $health = $client->cluster()->health()->asArray();
            $stats = $client->indices()->stats(['index' => implode(',', $names), 'metric' => 'docs,store'])->asArray();
            $settings = $client->indices()->getSettings(['index' => implode(',', $names), 'name' => 'index.creation_date'])->asArray();
            $sources = $client->search([
                'index' => $names['docs'],
                'body' => ['size' => 0, 'aggs' => ['sources' => ['terms' => ['field' => 'source', 'size' => 20]]]],
            ])->asArray();
        } catch (Throwable $e) {
            return [...$empty, 'enabled' => true, 'error' => Str::limit($e->getMessage(), 160)];
        }

        $indices = [];
        foreach ($names as $key => $name) {
            $created = $settings[$name]['settings']['index']['creation_date'] ?? null;
            $indices[$key] = [
                'name' => $name,
                'docs' => (int) ($stats['indices'][$name]['primaries']['docs']['count'] ?? 0),
                'size_bytes' => (int) ($stats['indices'][$name]['primaries']['store']['size_in_bytes'] ?? 0),
                'created_at' => $created !== null ? CarbonImmutable::createFromTimestampMs((int) $created)->toDateTimeString() : null,
            ];
        }

        return [
            'enabled' => true,
            'error' => null,
            'health' => (string) ($health['status'] ?? 'unknown'),
            'indices' => $indices,
            'sources' => collect($sources['aggregations']['sources']['buckets'] ?? [])
                ->mapWithKeys(fn (array $b): array => [(string) $b['key'] => (int) $b['doc_count']])
                ->all(),
        ];
    }
}
