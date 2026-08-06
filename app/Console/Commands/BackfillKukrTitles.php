<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GraphRag\ElasticClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Corrects KUKR document titles against the record's own page.
 *
 * Two defects came out of the original scrape. Most KUKR documents were indexed
 * under their English title with `title_en` left empty, which costs recall on
 * Thai queries — the retriever weights `title^3` and `title_en^2`, so the Thai
 * form of the title was searchable nowhere. A smaller group was indexed under
 * the *proceedings* name instead of the paper's own, e.g. bibid 367811 was
 * stored as "KU สร้างสรรค์ข้าวไทย ศาสตร์แห่งแผ่นดิน" when the record is
 * "การผลิตกระดาษพิเศษจากฟางข้าวสำหรับใช้กรองน้ำมันพืช".
 *
 * The site's `og:title` is authoritative for both: it is the record's own title,
 * server-rendered per page. What happens to the indexed value depends on which
 * language it is in:
 *
 *   Latin-script indexed title  → moved to `title_en`, `title` becomes og:title.
 *                                 Both forms stay searchable.
 *   Thai indexed title that differs → replaced. A proceedings name is not a
 *                                 translation of anything, so keeping it would
 *                                 go on matching papers it does not describe.
 *
 * Requires corpus:backfill-kukr-urls to have run — it reads the page each `url`
 * now points at.
 *
 * Re-runnable: a document whose title already matches the site is skipped.
 */
final class BackfillKukrTitles extends Command
{
    protected $signature = 'corpus:backfill-kukr-titles
        {--dry-run : Classify and report without writing to Elasticsearch}
        {--limit=0 : Stop after this many documents (0 = all)}
        {--delay=400 : Milliseconds to wait between requests to kukr.lib.ku.ac.th}';

    protected $description = 'Correct KUKR titles from each record page, filing the English form under title_en';

    private const INDEX = 'ku_bcg_documents';

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
                '_source' => ['title', 'title_en', 'url'],
                'query' => ['term' => ['source' => 'KUKR']],
            ],
        ])->asArray()['hits']['hits'] ?? [];

        $this->info(sprintf('%d KUKR documents%s', count($hits), $dryRun ? ' (dry run)' : ''));

        $translated = 0;
        $replaced = 0;
        $unchanged = 0;
        $unreachable = 0;
        $processed = 0;

        foreach ($hits as $hit) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $source = $hit['_source'];
            $url = (string) ($source['url'] ?? '');
            $indexed = trim((string) ($source['title'] ?? ''));

            if (! str_contains($url, '/KUKR/Detail/info/')) {
                $unreachable++;

                continue;
            }

            $processed++;
            $siteTitle = $this->siteTitle($url);

            if ($siteTitle === null) {
                $unreachable++;
                $this->line("  <fg=red>fail</>  {$url}");
                usleep($delayUs);

                continue;
            }

            if ($this->same($siteTitle, $indexed)) {
                $unchanged++;
                usleep($delayUs);

                continue;
            }

            $doc = ['title' => $siteTitle];

            // Only a Latin-script indexed title is the English form of this record.
            // Anything else that differs is a wrong title, not a translation.
            if ($this->isLatin($indexed) && trim((string) ($source['title_en'] ?? '')) === '') {
                $doc['title_en'] = $indexed;
                $translated++;
                $tag = '<fg=green>en</>    ';
            } else {
                $replaced++;
                $tag = '<fg=yellow>fix</>   ';
            }

            $this->line(sprintf('  %s %s', $tag, mb_substr($siteTitle, 0, 66)));
            if (! $this->isLatin($indexed)) {
                $this->line(sprintf('         was: %s', mb_substr($indexed, 0, 66)));
            }

            if (! $dryRun) {
                $client->update([
                    'index' => self::INDEX,
                    'id' => $hit['_id'],
                    'body' => ['doc' => $doc],
                ]);
            }

            usleep($delayUs);
        }

        if (! $dryRun && ($translated + $replaced) > 0) {
            $client->indices()->refresh(['index' => self::INDEX]);
        }

        $this->newLine();
        $this->table(
            ['english filed to title_en', 'wrong title replaced', 'already correct', 'unreachable'],
            [[$translated, $replaced, $unchanged, $unreachable]],
        );

        return self::SUCCESS;
    }

    /** Whether the two titles are the same string once case and spacing are ignored. */
    private function same(string $a, string $b): bool
    {
        $normalise = static fn (string $s): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)) ?? $s);

        return $normalise($a) === $normalise($b);
    }

    /** A title written in Latin script — the English form KUKR indexed instead of the Thai. */
    private function isLatin(string $title): bool
    {
        $letters = preg_match_all('/\p{L}/u', $title, $all);
        if ($letters === 0) {
            return false;
        }

        $thai = preg_match_all('/\p{Thai}/u', $title);

        return $thai / $letters < 0.2;
    }

    /** The record's own title, as the page reports it. */
    private function siteTitle(string $url): ?string
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

        $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));

        return $title === '' ? null : $title;
    }
}
