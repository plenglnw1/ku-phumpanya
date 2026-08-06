<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SearchHistory;
use App\Services\GraphRag\ElasticClientFactory;
use Illuminate\Console\Command;

/**
 * Repairs the document links inside already-cached search results.
 *
 * `search_histories.result` stores a full roadmap, so every module and evidence
 * item carries the URL and title that were correct at the time it was generated.
 * The corpus repairs — KU Forest permalinks, the KUKR path move, the KUKR title
 * corrections — only reach new searches; a roadmap the user opened last week
 * keeps its dead links until this runs.
 *
 * Nothing is re-generated: each cached entry is matched to its corpus document
 * and only the `url` and `title` fields are refreshed. Matching is by URL where
 * that is unambiguous (KUKR bibids survive the path move) and by exact title for
 * KU Forest, whose old URL was shared by every document under one crawl keyword
 * and so cannot identify anything on its own.
 *
 * Run after corpus:backfill-forest-urls, corpus:backfill-kukr-urls and
 * corpus:backfill-kukr-titles. Re-runnable — rows with nothing stale are skipped.
 */
final class RefreshCachedLinks extends Command
{
    protected $signature = 'corpus:refresh-cached-links
        {--dry-run : Report what would change without writing to the database}
        {--keep-unmatched : Leave stale links that match no corpus document instead of dropping them}';

    protected $description = 'Point cached search results at the repaired corpus URLs and titles';

    private const INDEX = 'ku_bcg_documents';

    private const RETIRED_KUKR = '#/KUKR/Search/detail/(\d+)#';

    private const CURRENT_KUKR = 'https://kukr.lib.ku.ac.th/KUKR/Detail/info/';

    private const FOREST_LISTING = 'research.ku.ac.th/forest/Search.aspx?keyword=';

    /**
     * Fields a cached entry copies from its corpus document once matched.
     *
     * `authors` is included because the KU Forest scrape kept only the principal
     * investigator, and the enrichment pass replaced it with the full team.
     */
    private const CARRIED = ['authors', 'category', 'project_nature', 'budget_year', 'funding', 'department'];

    /** url => corpus document, for entries matched by URL. */
    private array $docByUrl = [];

    /** normalised title => url, for KU Forest entries whose old URL identifies nothing. */
    private array $urlByTitle = [];

    private bool $keepUnmatched = false;

    public function handle(ElasticClientFactory $factory): int
    {
        $client = $factory->make();
        if ($client === null) {
            $this->error('Elasticsearch is disabled — set ELASTICSEARCH_ENABLED=true.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->keepUnmatched = (bool) $this->option('keep-unmatched');

        $hits = $client->search([
            'index' => self::INDEX,
            'body' => [
                'size' => 2000,
                '_source' => array_merge(['title', 'title_en', 'url', 'source'], self::CARRIED),
                'query' => ['match_all' => (object) []],
            ],
        ])->asArray()['hits']['hits'] ?? [];

        foreach ($hits as $hit) {
            $doc = $hit['_source'];
            $url = trim((string) ($doc['url'] ?? ''));
            $title = trim((string) ($doc['title'] ?? ''));
            if ($url === '' || $title === '') {
                continue;
            }

            $this->docByUrl[$url] = $doc;

            // Both title forms are indexed for KUKR; a cached entry may hold either.
            foreach ([$title, (string) ($doc['title_en'] ?? '')] as $form) {
                $key = $this->normalise($form);
                if ($key !== '') {
                    $this->urlByTitle[$key] ??= $url;
                }
            }
        }

        $this->info(sprintf('%d corpus documents loaded%s', count($hits), $dryRun ? ' (dry run)' : ''));

        $rowsChanged = 0;
        $urlsFixed = 0;
        $titlesFixed = 0;
        $fieldsFilled = 0;
        $unmatched = 0;

        SearchHistory::query()
            ->whereNotNull('result')
            ->orderBy('id')
            ->chunkById(50, function ($histories) use (&$rowsChanged, &$urlsFixed, &$titlesFixed, &$fieldsFilled, &$unmatched, $dryRun) {
                foreach ($histories as $history) {
                    $result = $history->result;
                    if (! is_array($result)) {
                        continue;
                    }

                    $urls = 0;
                    $titles = 0;
                    $fields = 0;
                    $missed = 0;

                    foreach ($result['learning_path']['phases'] ?? [] as $p => $phase) {
                        foreach ($phase['modules'] ?? [] as $m => $module) {
                            $fixed = $this->repair($module, $urls, $titles, $fields, $missed);
                            $result['learning_path']['phases'][$p]['modules'][$m] = $fixed;
                        }
                    }

                    foreach ($result['evidence'] ?? [] as $e => $item) {
                        $result['evidence'][$e] = $this->repair($item, $urls, $titles, $fields, $missed);
                    }

                    $urlsFixed += $urls;
                    $titlesFixed += $titles;
                    $fieldsFilled += $fields;
                    $unmatched += $missed;

                    $stripped = $this->keepUnmatched ? 0 : $missed;
                    if ($urls === 0 && $titles === 0 && $fields === 0 && $stripped === 0) {
                        continue;
                    }

                    $rowsChanged++;
                    $this->line(sprintf('  #%-4d %d url(s), %d title(s), %d field(s)%s', $history->id, $urls, $titles, $fields,
                        $missed > 0 ? sprintf(' — %d %s', $missed, $this->keepUnmatched ? 'unmatched' : 'dropped') : ''));

                    if (! $dryRun) {
                        $history->result = $result;
                        $history->save();
                    }
                }
            });

        $this->newLine();
        $this->table(
            ['rows changed', 'urls fixed', 'titles fixed', 'fields filled', $this->keepUnmatched ? 'left unmatched' : 'links dropped'],
            [[$rowsChanged, $urlsFixed, $titlesFixed, $fieldsFilled, $unmatched]],
        );

        return self::SUCCESS;
    }

    /**
     * One cached module or evidence item, with its link and title brought up to date.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function repair(array $entry, int &$urls, int &$titles, int &$fields, int &$missed): array
    {
        $url = trim((string) ($entry['url'] ?? ''));
        if ($url === '') {
            return $entry;
        }

        $resolved = $this->resolveUrl($url, (string) ($entry['title'] ?? ''));

        if ($resolved === null) {
            if (! $this->isStale($url)) {
                return $entry;
            }

            $missed++;

            // These are phase-level entries — a topic name, not a paper — that were
            // given the crawl keyword's listing URL. No document exists behind them,
            // so the link can only ever open a result page about something else.
            if (! $this->keepUnmatched) {
                unset($entry['url']);
            }

            return $entry;
        }

        if ($resolved !== $url) {
            $entry['url'] = $resolved;
            $urls++;
        }

        $doc = $this->docByUrl[$resolved] ?? null;
        if ($doc === null) {
            return $entry;
        }

        $corpusTitle = trim((string) ($doc['title'] ?? ''));
        if ($corpusTitle !== '' && trim((string) ($entry['title'] ?? '')) !== $corpusTitle) {
            $entry['title'] = $corpusTitle;
            $titles++;
        }

        // Fields the corpus gained after this roadmap was generated. Without this
        // an old KU Forest card keeps rendering an empty body even though its
        // project details now exist.
        foreach (self::CARRIED as $field) {
            $value = $doc[$field] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (($entry[$field] ?? null) !== $value) {
                $entry[$field] = $value;
                $fields++;
            }
        }

        return $entry;
    }

    /** The URL this entry should point at now, or null when nothing matches it. */
    private function resolveUrl(string $url, string $title): ?string
    {
        if (preg_match(self::RETIRED_KUKR, $url, $m) === 1) {
            $candidate = self::CURRENT_KUKR.$m[1];

            return isset($this->docByUrl[$candidate]) ? $candidate : null;
        }

        if (str_contains($url, self::FOREST_LISTING)) {
            return $this->urlByTitle[$this->normalise($title)] ?? null;
        }

        // Already current, or a link this corpus never owned.
        return isset($this->docByUrl[$url]) ? $url : null;
    }

    private function isStale(string $url): bool
    {
        return preg_match(self::RETIRED_KUKR, $url) === 1 || str_contains($url, self::FOREST_LISTING);
    }

    private function normalise(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
