<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GraphRag\ElasticClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fills in the Thai project details KU Forest publishes but the scrape did not keep.
 *
 * KU Forest carries no abstract for a project, so every one of the 203 KU_Forest
 * documents renders a detail page with an empty body. What the project page does
 * carry is entirely in Thai and entirely factual: the full research team, the
 * project's nature and category, the budget year with its span, and the funder
 * together with the department. The original scrape kept only the principal
 * investigator and a truncated funder, so most of that never reached the index.
 *
 * Requires corpus:backfill-forest-urls to have run — the pages are only reachable
 * through the permalinks it recovered.
 *
 * Re-runnable: a document that already has a team and a category is skipped.
 */
final class EnrichForestProjects extends Command
{
    protected $signature = 'corpus:enrich-forest-projects
        {--dry-run : Parse and report without writing to Elasticsearch}
        {--limit=0 : Stop after this many documents (0 = all)}
        {--delay=700 : Milliseconds to wait between requests to research.ku.ac.th}
        {--force : Re-read documents that already carry project details}';

    protected $description = 'Add the Thai project details (team, category, funder) to KU_Forest documents';

    private const INDEX = 'ku_bcg_documents';

    /**
     * Field labels in the order the page prints them. Each value runs until the
     * next label, so the list has to stay complete and in order.
     */
    private const LABELS = [
        'funding' => 'แหล่งทุน:',
        'department' => 'หน่วยงาน:',
        'project_nature' => 'ลักษณะโครงการ:',
        'budget_year' => 'ปีงบประมาณ:',
        'lead' => 'หัวหน้าโครงการ:',
        'team' => 'ผู้ร่วมโครงการ:',
        'category' => 'ประเภทโครงการ:',
        'end' => 'ลิงก์ที่เกี่ยวข้อง:',
    ];

    public function handle(ElasticClientFactory $factory): int
    {
        $client = $factory->make();
        if ($client === null) {
            $this->error('Elasticsearch is disabled — set ELASTICSEARCH_ENABLED=true.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');
        $delayUs = max(0, (int) $this->option('delay')) * 1000;

        $hits = $client->search([
            'index' => self::INDEX,
            'body' => [
                'size' => 1000,
                '_source' => ['title', 'url', 'authors', 'category'],
                'query' => ['bool' => [
                    'filter' => [['term' => ['source' => 'KU_Forest']]],
                    'must' => [['wildcard' => ['url' => '*Project.aspx*']]],
                ]],
            ],
        ])->asArray()['hits']['hits'] ?? [];

        $this->info(sprintf('%d KU_Forest project pages%s', count($hits), $dryRun ? ' (dry run)' : ''));

        $enriched = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;

        foreach ($hits as $hit) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $source = $hit['_source'];

            if (! $force && trim((string) ($source['category'] ?? '')) !== '') {
                $skipped++;

                continue;
            }

            $processed++;
            $fields = $this->parse((string) $source['url']);

            if ($fields === null) {
                $failed++;
                $this->line("  <fg=red>fail</> {$source['url']}");
                usleep($delayUs);

                continue;
            }

            // The page always lists the lead again inside the team, so the team is
            // the complete list when it parses and the lead is the fallback.
            $team = $fields['team'] ?: array_filter([$fields['lead']]);
            $doc = array_filter([
                'authors' => $team ?: null,
                'category' => $fields['category'] ?: null,
                'project_nature' => $fields['project_nature'] ?: null,
                'budget_year' => $fields['budget_year'] ?: null,
                'funding' => $fields['funding'] ?: null,
                'department' => $fields['department'] ?: null,
            ], static fn ($v) => $v !== null);

            if ($doc === []) {
                $failed++;
                usleep($delayUs);

                continue;
            }

            $enriched++;
            $this->line(sprintf(
                '  <fg=green>ok</>   %d คน · %s · %s',
                count($team),
                $fields['category'] ?: '-',
                mb_substr($fields['funding'] ?: '-', 0, 40),
            ));

            if (! $dryRun) {
                $client->update([
                    'index' => self::INDEX,
                    'id' => $hit['_id'],
                    'body' => ['doc' => $doc],
                ]);
            }

            usleep($delayUs);
        }

        if (! $dryRun && $enriched > 0) {
            $client->indices()->refresh(['index' => self::INDEX]);
        }

        $this->newLine();
        $this->table(
            ['enriched', 'already had details', 'failed'],
            [[$enriched, $skipped, $failed]],
        );

        return self::SUCCESS;
    }

    /**
     * @return array{funding: string, department: string, project_nature: string, budget_year: string, lead: string, team: list<string>, category: string}|null
     */
    private function parse(string $url): ?array
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

        $text = $this->plainText($response->body());
        $values = [];

        foreach (self::LABELS as $key => $label) {
            $values[$key] = $this->between($text, $label);
        }

        if ($values['category'] === '' && $values['team'] === '' && $values['lead'] === '') {
            return null;
        }

        return [
            'funding' => $values['funding'],
            'department' => $values['department'],
            'project_nature' => $values['project_nature'],
            'budget_year' => $values['budget_year'],
            'lead' => $values['lead'],
            'team' => $this->names($values['team']),
            'category' => $values['category'],
        ];
    }

    /** The value printed after `$label`, ending where the next known label begins. */
    private function between(string $text, string $label): string
    {
        $start = mb_strpos($text, $label);
        if ($start === false) {
            return '';
        }

        $from = $start + mb_strlen($label);
        $end = mb_strlen($text);

        foreach (self::LABELS as $other) {
            if ($other === $label) {
                continue;
            }
            $at = mb_strpos($text, $other, $from);
            if ($at !== false && $at < $end) {
                $end = $at;
            }
        }

        return trim(mb_substr($text, $from, $end - $from));
    }

    /**
     * @return list<string>
     */
    private function names(string $raw): array
    {
        $parts = preg_split('/\s*,\s*(?=(?:นาย|นาง|นางสาว|ดร\.|ผศ|รศ|ศ\.|Dr|Mr|Ms|Mrs|[A-Z]))|\s+,\s+/u', $raw) ?: [];

        $names = [];
        foreach ($parts as $part) {
            $name = trim($part, " \t\n\r-,");
            // Team rows print "-" for a member the record does not name.
            if ($name === '' || mb_strlen($name) < 3) {
                continue;
            }
            if (! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function plainText(string $html): string
    {
        $body = preg_replace('/(?is)<script.*?<\/script>|<style.*?<\/style>/', '', $html) ?? $html;
        $text = preg_replace('/(?s)<[^>]+>/', ' ', $body) ?? $body;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
