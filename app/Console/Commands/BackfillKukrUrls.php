<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GraphRag\ElasticClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Repoints KUKR document links at the site's current detail path.
 *
 * KUKR moved its record pages from `/KUKR/Search/detail/{bibid}` to
 * `/KUKR/Detail/info/{bibid}`, so every one of the 403 indexed KUKR links now
 * 404s. The bibid itself is unchanged — the SPA still embeds the same id — so
 * the repair is a path rewrite rather than a re-scrape.
 *
 * Each candidate is fetched before it is written: the new page must answer 200
 * and carry an `og:title`, which is server-rendered per record. A document whose
 * replacement fails that check keeps its current URL rather than trading one
 * broken link for another.
 *
 * Re-runnable: documents already on the new path are skipped.
 */
final class BackfillKukrUrls extends Command
{
    protected $signature = 'corpus:backfill-kukr-urls
        {--dry-run : Verify and report without writing to Elasticsearch}
        {--limit=0 : Stop after this many documents (0 = all)}
        {--delay=400 : Milliseconds to wait between requests to kukr.lib.ku.ac.th}';

    protected $description = 'Repoint KUKR links from the retired /Search/detail path to /Detail/info';

    private const INDEX = 'ku_bcg_documents';

    private const RETIRED = '/KUKR/Search/detail/';

    private const CURRENT = 'https://kukr.lib.ku.ac.th/KUKR/Detail/info/';

    public function handle(ElasticClientFactory $factory): int
    {
        $client = $factory->make();
        if ($client === null) {
            $this->error('Elasticsearch is disabled — set ELASTICSEARCH_ENABLED=true.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $delayUs = max(0, (int) $this->option('delay')) * 1000;

        $hits = $client->search([
            'index' => self::INDEX,
            'body' => [
                'size' => 1000,
                '_source' => ['title', 'url'],
                'query' => ['term' => ['source' => 'KUKR']],
            ],
        ])->asArray()['hits']['hits'] ?? [];

        if ($hits === []) {
            $this->warn('No KUKR documents found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d KUKR documents%s', count($hits), $dryRun ? ' (dry run)' : ''));

        $updated = 0;
        $skipped = 0;
        $unverified = 0;
        $noBibid = 0;
        $processed = 0;

        foreach ($hits as $hit) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $current = (string) ($hit['_source']['url'] ?? '');

            if (! str_contains($current, self::RETIRED)) {
                $skipped++;

                continue;
            }

            $bibid = $this->bibid($current);
            if ($bibid === null) {
                $noBibid++;
                $this->line("  <fg=red>skip</> no bibid in {$current}");

                continue;
            }

            $processed++;
            $candidate = self::CURRENT.$bibid;
            $title = $this->verify($candidate);

            if ($title === null) {
                $unverified++;
                $this->line("  <fg=red>fail</>  {$bibid} — left unchanged");
                usleep($delayUs);

                continue;
            }

            $updated++;
            $this->line(sprintf('  <fg=green>ok</>    %s  %s', $bibid, mb_substr($title, 0, 70)));

            if (! $dryRun) {
                $client->update([
                    'index' => self::INDEX,
                    'id' => $hit['_id'],
                    'body' => ['doc' => ['url' => $candidate]],
                ]);
            }

            usleep($delayUs);
        }

        if (! $dryRun && $updated > 0) {
            $client->indices()->refresh(['index' => self::INDEX]);
        }

        $this->newLine();
        $this->table(
            ['repointed', 'already ok', 'failed verify', 'no bibid'],
            [[$updated, $skipped, $unverified, $noBibid]],
        );

        return self::SUCCESS;
    }

    /** The record id, which the retired path carried as its last segment. */
    private function bibid(string $url): ?string
    {
        return preg_match('#/KUKR/Search/detail/(\d+)#', $url, $m) === 1 ? $m[1] : null;
    }

    /**
     * The record's title as the site reports it, or null when the page does not
     * resolve. The SPA renders its body client-side, so `og:title` is the only
     * per-record text in the served HTML — which makes it the proof that this
     * bibid is a real record and not a generic shell.
     */
    private function verify(string $url): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(30)
                ->retry(2, 1000, throw: false)
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (preg_match('/og:title"\s*content="([^"]+)"/', $response->body(), $m) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode($m[1]));

        return $title === '' ? null : $title;
    }
}
