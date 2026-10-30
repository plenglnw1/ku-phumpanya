<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Admin\SystemMetrics;
use App\Services\GraphRag\ElasticClientFactory;
use App\Services\GraphRag\HybridRetriever;
use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Offline Recall@K of HybridRetriever against config/retrieval_eval.php. Each topic
 * is one small search, so it is cheap enough for the KU host, but it reads whatever
 * index this environment points at.
 */
final class EvaluateRetrieval extends Command
{
    protected $signature = 'eval:retrieval
        {--k= : Cut-off rank (defaults to retrieval_eval.k)}
        {--dry-run : Print the scores without saving them for the dashboard}';

    protected $description = 'Measure Recall@K of the retriever on the six BCG topics and save the result for the System dashboard';

    public function handle(HybridRetriever $retriever, ElasticClientFactory $factory): int
    {
        $k = max(1, (int) ($this->option('k') ?? config('retrieval_eval.k', 10)));
        $topics = (array) config('retrieval_eval.topics');
        if ($topics === []) {
            $this->error('config/retrieval_eval.php has no topics.');

            return self::FAILURE;
        }

        $client = $factory->make();
        $results = [];
        foreach ($topics as $topicId => $topic) {
            $results[(string) $topicId] = $this->evaluate((string) $topicId, $topic, $k, $retriever, $client);
        }

        $recall = round(array_sum(array_column($results, 'recall')) / count($results), 4);

        $this->table(
            ['Topic', 'Mode', 'Query', 'Hits', 'Relevant', "Recall@{$k}"],
            array_map(fn (string $id, array $r): array => [
                $id, $r['mode'], $r['query'], $r['hits'], $r['relevant'], number_format($r['recall'] * 100, 1).'%',
            ], array_keys($results), $results),
        );
        $this->info(sprintf('Mean Recall@%d: %.1f%%', $k, $recall * 100));

        if (! $this->option('dry-run')) {
            Storage::disk('local')->put(SystemMetrics::RECALL_REPORT, json_encode([
                'ran_at' => now()->toIso8601String(),
                'k' => $k,
                'recall' => $recall,
                'topics' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('Saved for the System dashboard.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{query: string, relevant_urls?: list<string>}  $topic
     * @return array{query: string, mode: string, hits: int, relevant: int, recall: float}
     */
    private function evaluate(string $topicId, array $topic, int $k, HybridRetriever $retriever, ?Client $client): array
    {
        $retrieved = array_slice($retriever->retrieve($topic['query'], $k), 0, $k);
        $urls = array_values(array_filter((array) ($topic['relevant_urls'] ?? [])));

        if ($urls !== []) {
            $hits = count(array_intersect(array_unique(array_column($retrieved, 'url')), $urls));

            return [
                'query' => $topic['query'],
                'mode' => 'urls',
                'hits' => $hits,
                'relevant' => count($urls),
                'recall' => round($hits / min($k, count($urls)), 4),
            ];
        }

        $hits = count(array_filter(
            $retrieved,
            fn (array $doc): bool => in_array($topicId, array_map('strval', (array) ($doc['topic_ids'] ?? [])), true),
        ));
        $relevant = $this->taggedCount($client, $topicId) ?? $k;

        return [
            'query' => $topic['query'],
            'mode' => 'topic_id',
            'hits' => $hits,
            'relevant' => $relevant,
            'recall' => $relevant > 0 ? round($hits / min($k, $relevant), 4) : 0.0,
        ];
    }

    private function taggedCount(?Client $client, string $topicId): ?int
    {
        if (! $client instanceof Client) {
            return null;
        }

        try {
            return (int) ($client->count([
                'index' => config('elasticsearch.indices.docs'),
                'body' => ['query' => ['term' => ['topic_ids' => $topicId]]],
            ])->asArray()['count'] ?? 0);
        } catch (Throwable) {
            return null;
        }
    }
}
