<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A user's interest profile, derived entirely from what they have actually done.
 *
 * The only durable behavioural signal in the system is `search_histories`: each row
 * holds the query plus the full GraphRAG result (title, evidence, phase names), so
 * it records both what was asked and what was returned. `activity_logs` only tracks
 * auth events and there is no progress/view table, so neither contributes.
 *
 * Every signal is weighted by exponential recency decay, which is what makes the
 * recommendations move as the user's behaviour moves: an interest from two months
 * ago counts for a fraction of one from yesterday, without ever being deleted.
 */
final class InterestProfile
{
    /** Signals older than this contribute half as much as a signal from today. */
    private const HALF_LIFE_DAYS = 14.0;

    /** How many recent searches to read. Beyond this, decay has made them negligible. */
    private const HISTORY_LIMIT = 40;

    /**
     * @param  array<string, float>  $phrases      free-text signals → weight
     * @param  array<string, float>  $topics       topic name → weight
     * @param  array<string, float>  $pillars      BCG pillar → weight
     * @param  array<string, int>    $queryCounts  raw query → times searched
     * @param  list<string>          $seenUrls     documents already shown as evidence
     */
    public function __construct(
        public readonly array $phrases,
        public readonly array $topics,
        public readonly array $pillars,
        public readonly array $queryCounts,
        public readonly array $seenUrls,
        public readonly ?string $faculty,
        public readonly ?string $department,
        public readonly int $searchCount,
    ) {}

    public function isCold(): bool
    {
        return $this->searchCount === 0;
    }

    /** The user's most recent distinct query, used to explain a match in their own words. */
    public function topPhrases(int $limit): array
    {
        $sorted = $this->phrases;
        arsort($sorted);

        return array_slice($sorted, 0, $limit, true);
    }

    public function topTopics(int $limit): array
    {
        $sorted = $this->topics;
        arsort($sorted);

        return array_slice($sorted, 0, $limit, true);
    }

    public function topPillars(int $limit): array
    {
        $sorted = $this->pillars;
        arsort($sorted);

        return array_slice($sorted, 0, $limit, true);
    }

    /**
     * Rows created before phases were grouped by subject carry generic names like
     * "Phase: Foundation", which describe the pipeline rather than an interest.
     */
    private static function normalisePhaseName(string $raw): ?string
    {
        $name = trim(preg_replace('/^phase\s*\d*\s*[:\-–]\s*/i', '', $raw) ?? $raw);
        $generic = ['foundation', 'core', 'advanced', 'intermediate', 'basics', 'introduction'];

        if ($name === '' || in_array(mb_strtolower($name), $generic, true)) {
            return null;
        }

        return $name;
    }

    public static function build(User $user): self
    {
        $histories = SearchHistory::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(self::HISTORY_LIMIT)
            ->get();

        $phrases = [];
        $topics = [];
        $pillars = [];
        $queryCounts = [];
        $seenUrls = [];

        $now = Carbon::now();

        foreach ($histories as $history) {
            $ageDays = max(0.0, $now->diffInRealHours($history->created_at, absolute: true) / 24);
            $weight = 2 ** (-$ageDays / self::HALF_LIFE_DAYS);

            $query = trim((string) $history->query);
            if ($query !== '') {
                // Repeat searches accumulate, so a returning interest outranks a one-off.
                $phrases[$query] = ($phrases[$query] ?? 0) + $weight * 3.0;
                $queryCounts[$query] = ($queryCounts[$query] ?? 0) + 1;
            }

            $result = $history->result;
            if (! is_array($result)) {
                continue;
            }

            $title = trim((string) ($result['title'] ?? ''));
            if ($title !== '') {
                $phrases[$title] = ($phrases[$title] ?? 0) + $weight * 1.0;
            }

            // Evidence is what the user was actually shown — a stronger signal of
            // subject matter than the query string alone, which is often vague.
            foreach (array_slice($result['evidence'] ?? [], 0, 5) as $item) {
                $evidenceTitle = trim((string) ($item['title'] ?? ''));
                if ($evidenceTitle !== '') {
                    $phrases[$evidenceTitle] = ($phrases[$evidenceTitle] ?? 0) + $weight * 0.6;
                }
                $url = trim((string) ($item['url'] ?? ''));
                if ($url !== '') {
                    $seenUrls[] = $url;
                }
            }

            foreach ($result['learning_path']['phases'] ?? [] as $phase) {
                $name = self::normalisePhaseName((string) ($phase['name'] ?? ''));
                if ($name !== null) {
                    $topics[$name] = ($topics[$name] ?? 0) + $weight;
                }
            }

            foreach ($result['knowledge_graph']['nodes'] ?? [] as $node) {
                if (($node['type'] ?? '') === 'bcg_pillar') {
                    $label = trim((string) ($node['label'] ?? ''));
                    if ($label !== '') {
                        $pillars[$label] = ($pillars[$label] ?? 0) + $weight;
                    }
                }
            }
        }

        return new self(
            phrases: $phrases,
            topics: $topics,
            pillars: $pillars,
            queryCounts: $queryCounts,
            seenUrls: array_values(array_unique($seenUrls)),
            faculty: $user->faculty,
            department: $user->department,
            searchCount: $histories->count(),
        );
    }
}
