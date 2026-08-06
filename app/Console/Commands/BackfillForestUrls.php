<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GraphRag\ElasticClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Replaces the crawler's listing URL on KU_Forest documents with the record's
 * own permalink.
 *
 * The scrape stored `Search.aspx?keyword={search_keyword}` on every document it
 * found under that keyword, so all 203 KU_Forest records share only 22 URLs and
 * a card opens a result list of hundreds of unrelated projects. KUKR and KU_MOOC
 * already carry real per-document links and are left alone.
 *
 * No project id was captured at scrape time, so the permalink is recovered by
 * searching KU Forest for the record's own title, which returns it as the single
 * hit, and reading the detail link off that page.
 *
 * Re-runnable: a document whose URL is already a permalink is skipped, so this
 * can be run again after any future ingest.
 */
final class BackfillForestUrls extends Command
{
    protected $signature = 'corpus:backfill-forest-urls
        {--dry-run : Resolve and report without writing to Elasticsearch}
        {--limit=0 : Stop after this many documents (0 = all)}
        {--delay=800 : Milliseconds to wait between requests to research.ku.ac.th}';

    protected $description = 'Replace KU_Forest listing URLs with per-document permalinks';

    private const INDEX = 'ku_bcg_documents';

    private const BASE = 'https://research.ku.ac.th/forest/';

    /** The listing URL the scraper wrote — the only URL shape worth replacing. */
    private const LISTING = 'Search.aspx?keyword=';

    /** Detail pages KU Forest links to from a result row, in the order we prefer them. */
    private const DETAIL = ['Project', 'Intellectual', 'Award', 'Journal', 'Conference'];

    /** Which detail page a document type belongs on, when the search offers a choice. */
    private const PREFERRED = [
        'งานวิจัย' => 'Project',
        'ทรัพย์สินทางปัญญา' => 'Intellectual',
        'รางวัล' => 'Award',
    ];

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

        $response = $client->search([
            'index' => self::INDEX,
            'body' => [
                'size' => 1000,
                '_source' => ['title', 'doc_type', 'url', 'search_keyword'],
                'query' => ['term' => ['source' => 'KU_Forest']],
            ],
        ]);

        $hits = $response->asArray()['hits']['hits'] ?? [];
        if ($hits === []) {
            $this->warn('No KU_Forest documents found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d KU_Forest documents%s', count($hits), $dryRun ? ' (dry run)' : ''));

        $resolved = 0;
        $narrowed = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;

        foreach ($hits as $hit) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $source = $hit['_source'];
            $current = (string) ($source['url'] ?? '');

            // Already a permalink — from an earlier run, or never broken to begin with.
            if (! str_contains($current, self::LISTING)) {
                $skipped++;

                continue;
            }

            $title = $this->cleanTitle((string) ($source['title'] ?? ''));
            if (mb_strlen($title) < 8) {
                $failed++;
                $this->line("  <fg=red>skip</>  title too short to search: {$current}");

                continue;
            }

            $processed++;
            $permalink = $this->resolve($title, (string) ($source['doc_type'] ?? ''));

            // Even without a detail link, searching the title beats searching the
            // crawl keyword: one or two hits instead of several hundred.
            $url = $permalink ?? self::BASE.self::LISTING.rawurlencode($title);
            $permalink === null ? $narrowed++ : $resolved++;

            $this->line(sprintf(
                '  <fg=%s>%s</> %s',
                $permalink === null ? 'yellow' : 'green',
                $permalink === null ? 'narrow' : 'exact ',
                mb_substr($permalink ?? $title, 0, 90),
            ));

            if (! $dryRun) {
                $client->update([
                    'index' => self::INDEX,
                    'id' => $hit['_id'],
                    'body' => ['doc' => ['url' => $url]],
                ]);
            }

            usleep($delayUs);
        }

        if (! $dryRun && $processed > 0) {
            $client->indices()->refresh(['index' => self::INDEX]);
        }

        $this->newLine();
        $this->table(
            ['permalink', 'narrowed to title', 'already ok', 'unsearchable'],
            [[$resolved, $narrowed, $skipped, $failed]],
        );

        return self::SUCCESS;
    }

    /**
     * Award and intellectual-property rows were scraped as whole listing blobs —
     * "(category) title (year) นักวิจัย: … Doner: …" — which match nothing when
     * searched verbatim, and are long enough that the site answers 404.
     */
    private function cleanTitle(string $raw): string
    {
        $title = preg_replace('/^\([^)]{0,40}\)\s*/u', '', trim($raw)) ?? $raw;
        $title = preg_split('/\s*\(\d{4}\)\s*/u', $title)[0] ?? $title;

        return trim($title, " \t\n\r\0\x0B\"'");
    }

    /**
     * The record's own KU Forest page, or null when no query finds a detail link.
     *
     * Tried in order, because the site's stored title is not always byte-identical
     * to the scraped one: the full title; the title without its trailing
     * parenthetical (KU Forest often omits a "(case study …)" suffix); and a
     * leading fragment, for titles the scrape truncated.
     */
    private function resolve(string $title, string $docType): ?string
    {
        $candidates = [$title];

        $withoutSuffix = trim(mb_substr($title, 0, (int) mb_strrpos($title, '(') ?: mb_strlen($title)));
        if ($withoutSuffix !== '' && $withoutSuffix !== $title && mb_strlen($withoutSuffix) >= 8) {
            $candidates[] = $withoutSuffix;
        }

        if (mb_strlen($title) > 70) {
            $candidates[] = trim(mb_substr($title, 0, 60));
        }

        foreach ($candidates as $i => $candidate) {
            if ($i > 0) {
                usleep(400_000);
            }
            $link = $this->search($candidate, $docType);
            if ($link !== null) {
                return $link;
            }
        }

        return null;
    }

    /** One search against KU Forest, returning the best detail link on the page. */
    private function search(string $title, string $docType): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(30)
                ->retry(2, 1000, throw: false)
                ->get(self::BASE.'Search.aspx', ['keyword' => $title]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $html = $response->body();
        $pattern = '/href="((?:'.implode('|', self::DETAIL).')\.aspx[^"]*)"/';
        if (preg_match_all($pattern, $html, $matches) === 0) {
            return null;
        }

        $links = $matches[1];
        $preferred = self::PREFERRED[$docType] ?? null;

        if ($preferred !== null) {
            foreach ($links as $link) {
                if (str_starts_with($link, $preferred.'.aspx')) {
                    return self::BASE.html_entity_decode($link);
                }
            }
        }

        return self::BASE.html_entity_decode($links[0]);
    }
}
