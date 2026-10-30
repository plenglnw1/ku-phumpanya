<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\LearningProgress;
use App\Models\SearchHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Usage numbers for the admin Business dashboard. Admin accounts are excluded so
 * testing the site does not inflate adoption.
 */
final class BusinessMetrics
{
    /**
     * @return array{active_7d: int, active_30d: int, searches_7d: int, searches_30d: int, zero_result_rate_30d: float, consent_pct: float, learning_started_pct_30d: float, cards_completed_30d: int, users_total: int}
     */
    public function summary(): array
    {
        $searches30 = $this->searches(30)->count();
        $zero30 = $this->searches(30)->where('status', 'zero_results')->count();

        $answered = $this->searches(30)->where('status', 'success')->pluck('id');
        $started = $answered->isEmpty() ? 0 : LearningProgress::query()
            ->whereIn('search_history_id', $answered)
            ->distinct()
            ->count('search_history_id');

        $users = $this->users();
        $usersTotal = (clone $users)->count();
        $consented = (clone $users)
            ->whereNotNull('consented_at')
            ->where('consent_version', (string) config('pdpa.consent_version'))
            ->count();

        return [
            'active_7d' => $this->activeUsers(7),
            'active_30d' => $this->activeUsers(30),
            'searches_7d' => $this->searches(7)->count(),
            'searches_30d' => $searches30,
            'zero_result_rate_30d' => self::pct($zero30, $searches30),
            'consent_pct' => self::pct($consented, $usersTotal),
            'learning_started_pct_30d' => self::pct($started, $answered->count()),
            'cards_completed_30d' => LearningProgress::query()
                ->where('completed_at', '>=', $this->since(30))
                ->whereIn('user_id', $this->users()->select('id'))
                ->count(),
            'users_total' => $usersTotal,
        ];
    }

    /**
     * Users who searched or completed a learning card in the window.
     */
    public function activeUsers(int $days): int
    {
        $searchers = $this->searches($days)->distinct()->pluck('user_id');
        $learners = LearningProgress::query()
            ->where('completed_at', '>=', $this->since($days))
            ->whereIn('user_id', $this->users()->select('id'))
            ->distinct()
            ->pluck('user_id');

        return $searchers->merge($learners)->unique()->count();
    }

    /**
     * @return list<array{date: string, searches: int, users: int}>
     */
    public function searchesPerDay(int $days = 30): array
    {
        $byDay = $this->searches($days)
            ->get(['user_id', 'created_at'])
            ->groupBy(fn (SearchHistory $h): string => $h->created_at->toDateString());

        $start = $this->since($days);

        return collect(range(0, $days - 1))
            ->map(function (int $i) use ($start, $byDay): array {
                $date = $start->addDays($i)->toDateString();
                $rows = $byDay->get($date, collect());

                return ['date' => $date, 'searches' => $rows->count(), 'users' => $rows->pluck('user_id')->unique()->count()];
            })
            ->all();
    }

    /**
     * @return list<array{week: string, users: int}>
     */
    public function newUsersPerWeek(int $weeks = 8): array
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1);
        $byWeek = $this->users()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->countBy(fn (User $u): string => $u->created_at->startOfWeek()->toDateString());

        return collect(range(0, $weeks - 1))
            ->map(function (int $i) use ($start, $byWeek): array {
                $week = $start->addWeeks($i)->toDateString();

                return ['week' => $week, 'users' => (int) $byWeek->get($week, 0)];
            })
            ->all();
    }

    /**
     * @return list<array{query: string, count: int, zero_results: int}>
     */
    public function topQueries(int $days = 30, int $limit = 10): array
    {
        return $this->searches($days)
            ->get(['query', 'status'])
            ->groupBy(fn (SearchHistory $h): string => Str::lower(Str::squish($h->query)))
            ->map(fn (Collection $rows, string $query): array => [
                'query' => $query,
                'count' => $rows->count(),
                'zero_results' => $rows->where('status', 'zero_results')->count(),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Concepts that most often appear in the graphs users were shown.
     *
     * @return array<string, int>
     */
    public function topConcepts(int $days = 30, int $limit = 10): array
    {
        return $this->searches($days)
            ->where('status', 'success')
            ->get(['metrics'])
            ->flatMap(fn (SearchHistory $h): array => array_map('strval', (array) ($h->metrics['top_node_labels'] ?? [])))
            ->filter(fn (string $label): bool => trim($label) !== '')
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function searchesByFaculty(int $days = 30, int $limit = 10): array
    {
        return $this->countBy('faculty_snapshot', $days, 'ไม่ระบุคณะ')->take($limit)->all();
    }

    /**
     * @return array<string, int>
     */
    public function searchesByRole(int $days = 30): array
    {
        return $this->countBy('role_snapshot', $days, 'ไม่ระบุ')->all();
    }

    /**
     * @return Collection<string, int>
     */
    private function countBy(string $column, int $days, string $blank): Collection
    {
        return $this->searches($days)
            ->get([$column])
            ->countBy(fn (SearchHistory $h): string => trim((string) $h->{$column}) !== '' ? (string) $h->{$column} : $blank)
            ->sortDesc();
    }

    /**
     * @return Builder<SearchHistory>
     */
    private function searches(int $days): Builder
    {
        return SearchHistory::query()
            ->where('created_at', '>=', $this->since($days))
            ->where(fn (Builder $q) => $q->whereNull('role_snapshot')->orWhere('role_snapshot', '!=', UserRole::Admin->value));
    }

    /**
     * @return Builder<User>
     */
    private function users(): Builder
    {
        return User::query()->where('role', '!=', UserRole::Admin->value);
    }

    private function since(int $days): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($days - 1)->startOfDay();
    }

    private static function pct(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
