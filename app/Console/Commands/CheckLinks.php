<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SearchHistory;
use App\Services\GraphRag\ElasticClientFactory;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gate 1.4.4 evidence: every "view source" link the app can show answers HTTP 200.
 *
 * URLs come from the Elasticsearch corpus, the seed config and the evidence and
 * modules of stored search results. Unlike the KU-BCG crawler check this keeps
 * going after a failure, so the report gives a pass rate per source.
 */
final class CheckLinks extends Command
{
    protected $signature = 'links:check
        {--source= : Only check one source (KU_Forest, KUKR, KU_MOOC)}
        {--limit=0 : Stop after this many URLs (0 = all)}
        {--concurrency=4 : Parallel requests per batch}
        {--delay=500 : Milliseconds between batches, to stay polite to the KU sites}';

    protected $description = 'Check that source links (KU Forest, KUKR, KU MOOC) return HTTP 200 and write a report';

    private const SOURCES = ['KU_Forest', 'KUKR', 'KU_MOOC'];

    private const USER_AGENT = 'KU-Phumpanya-linkcheck/1.0';

    public function handle(ElasticClientFactory $factory): int
    {
        $only = $this->option('source');
        if ($only !== null && ! in_array($only, self::SOURCES, true)) {
            $this->error('--source must be one of '.implode(', ', self::SOURCES));

            return self::FAILURE;
        }

        $links = $this->collect($factory);
        if ($only !== null) {
            $links = array_filter($links, fn (string $source): bool => $source === $only);
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $links = array_slice($links, 0, $limit, true);
        }

        $this->info(sprintf('Checking %d URLs…', count($links)));

        $results = [];
        $bar = $this->output->createProgressBar(count($links));
        foreach (array_chunk($links, max(1, (int) $this->option('concurrency')), true) as $i => $batch) {
            if ($i > 0) {
                usleep(max(0, (int) $this->option('delay')) * 1000);
            }

            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $url) => $pool->as($url)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->timeout(20)
                    ->get($url),
                array_keys($batch),
            ));

            foreach ($batch as $url => $source) {
                $response = $responses[$url] ?? null;
                $results[] = [
                    'url' => $url,
                    'source' => $source,
                    'status' => $response instanceof Response ? $response->status() : null,
                    'error' => $response instanceof Throwable ? $response->getMessage() : null,
                ];
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine(2);

        $summary = $this->summarise($results);
        $this->table(['Source', 'Checked', 'HTTP 200', 'Failed', 'Pass %'], array_map(
            fn (string $source, array $row): array => [$source, $row['checked'], $row['ok'], $row['failed'], $row['percent']],
            array_keys($summary),
            $summary,
        ));

        $failures = array_values(array_filter($results, fn (array $r): bool => $r['status'] !== 200));
        foreach (array_slice($failures, 0, 20) as $failure) {
            $this->line(sprintf('  %s %s', $failure['status'] ?? 'ERR', $failure['url']));
        }

        $path = 'reports/links-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode([
            'checked_at' => now()->toIso8601String(),
            'summary' => $summary,
            'failures' => $failures,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info('Report: '.Storage::disk('local')->path($path));

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, string> url => source
     */
    private function collect(ElasticClientFactory $factory): array
    {
        $links = [];
        $add = function (?string $url, ?string $source) use (&$links): void {
            $url = trim((string) $url);
            if ($url !== '' && str_starts_with($url, 'http') && in_array($source, self::SOURCES, true)) {
                $links[$url] ??= $source;
            }
        };

        $client = $factory->make();
        if ($client !== null) {
            $hits = $client->search([
                'index' => config('elasticsearch.indices.docs'),
                'body' => ['size' => 2000, '_source' => ['url', 'source'], 'query' => ['match_all' => (object) []]],
            ])->asArray()['hits']['hits'] ?? [];
            foreach ($hits as $hit) {
                $add($hit['_source']['url'] ?? null, $hit['_source']['source'] ?? null);
            }
        } else {
            $this->warn('Elasticsearch is disabled; checking seed config and stored results only.');
        }

        foreach (config('graphrag_seed.topics', []) as $topic) {
            foreach ($topic['sources'] ?? [] as $source) {
                $add($source['url'] ?? null, $source['source'] ?? null);
            }
            foreach ($topic['courses'] ?? [] as $course) {
                $add($course['url'] ?? null, 'KU_MOOC');
            }
        }

        SearchHistory::query()->whereNotNull('result')->select(['id', 'result'])->chunkById(200, function ($histories) use ($add): void {
            foreach ($histories as $history) {
                foreach ($history->result['evidence'] ?? [] as $item) {
                    $add($item['url'] ?? null, $item['source'] ?? null);
                }
            }
        });

        return $links;
    }

    /**
     * @param  list<array{url: string, source: string, status: ?int, error: ?string}>  $results
     * @return array<string, array{checked: int, ok: int, failed: int, percent: float}>
     */
    private function summarise(array $results): array
    {
        $summary = [];
        foreach (self::SOURCES as $source) {
            $rows = array_filter($results, fn (array $r): bool => $r['source'] === $source);
            $ok = count(array_filter($rows, fn (array $r): bool => $r['status'] === 200));
            $summary[$source] = [
                'checked' => count($rows),
                'ok' => $ok,
                'failed' => count($rows) - $ok,
                'percent' => count($rows) > 0 ? round($ok / count($rows) * 100, 1) : 0.0,
            ];
        }

        return $summary;
    }
}
