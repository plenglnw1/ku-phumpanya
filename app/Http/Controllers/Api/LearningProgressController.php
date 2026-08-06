<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningProgress;
use App\Models\SearchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Completion state for the modules of one stored roadmap.
 *
 * The roadmap itself lives in `search_histories.result`, so a module is addressed
 * by its position there — the same (phase, card) pair the frontend already uses to
 * link a card to its detail page. That keeps progress valid without duplicating
 * the roadmap into a second table.
 */
final class LearningProgressController extends Controller
{
    public function show(Request $request, SearchHistory $searchHistory): JsonResponse
    {
        abort_unless($searchHistory->user_id === $request->user()->id, 403);

        return response()->json(['data' => $this->state($searchHistory, $request->user()->id)]);
    }

    public function update(Request $request, SearchHistory $searchHistory): JsonResponse
    {
        abort_unless($searchHistory->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'phase' => ['required', 'integer', 'min:0'],
            'card' => ['required', 'integer', 'min:0'],
            'completed' => ['required', 'boolean'],
        ]);

        $phase = (int) $validated['phase'];
        $card = (int) $validated['card'];

        // Without this a client could store progress against modules that do not
        // exist, which would push the percentage above 100 and never clear.
        if (! $this->moduleExists($searchHistory, $phase, $card)) {
            throw ValidationException::withMessages([
                'card' => 'ไม่พบโมดูลนี้ในเส้นทางการเรียนรู้',
            ]);
        }

        $keys = [
            'user_id' => $request->user()->id,
            'search_history_id' => $searchHistory->id,
            'phase_index' => $phase,
            'card_index' => $card,
        ];

        if ($validated['completed']) {
            LearningProgress::query()->updateOrCreate($keys, ['completed_at' => Carbon::now()]);
        } else {
            LearningProgress::query()->where($keys)->delete();
        }

        return response()->json(['data' => $this->state($searchHistory, $request->user()->id)]);
    }

    /**
     * @return array{completed: list<array{phase: int, card: int}>, completed_count: int, total: int, percent: int}
     */
    private function state(SearchHistory $searchHistory, int $userId): array
    {
        $rows = LearningProgress::query()
            ->where('user_id', $userId)
            ->where('search_history_id', $searchHistory->id)
            ->orderBy('phase_index')
            ->orderBy('card_index')
            ->get(['phase_index', 'card_index']);

        $total = $this->totalModules($searchHistory);

        // A roadmap can be re-resolved with fewer modules than when it was last
        // read, so stale rows are filtered out of the count instead of inflating it.
        $completed = $rows
            ->filter(fn ($row) => $this->moduleExists($searchHistory, (int) $row->phase_index, (int) $row->card_index))
            ->map(fn ($row) => ['phase' => (int) $row->phase_index, 'card' => (int) $row->card_index])
            ->values()
            ->all();

        $count = count($completed);

        return [
            'completed' => $completed,
            'completed_count' => $count,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function phases(SearchHistory $searchHistory): array
    {
        $result = $searchHistory->result;

        return is_array($result) ? array_values($result['learning_path']['phases'] ?? []) : [];
    }

    private function totalModules(SearchHistory $searchHistory): int
    {
        return array_sum(array_map(
            fn ($phase) => count($phase['modules'] ?? []),
            $this->phases($searchHistory),
        ));
    }

    private function moduleExists(SearchHistory $searchHistory, int $phase, int $card): bool
    {
        $phases = $this->phases($searchHistory);

        return isset($phases[$phase]['modules'][$card]);
    }
}
