<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One completed module inside a stored roadmap. The absence of a row means
 * "not done" — un-completing deletes rather than flipping a flag.
 */
class LearningProgress extends Model
{
    protected $table = 'learning_progress';

    protected $fillable = [
        'user_id',
        'search_history_id',
        'phase_index',
        'card_index',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase_index' => 'integer',
            'card_index' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function searchHistory(): BelongsTo
    {
        return $this->belongsTo(SearchHistory::class);
    }
}
