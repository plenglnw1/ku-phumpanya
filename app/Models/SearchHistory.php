<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    protected $fillable = [
        'user_id',
        'query',
        'result',
        'status',
        'query_type',
        'role_snapshot',
        'faculty_snapshot',
        'total_latency_ms',
        'retrieval_latency_ms',
        'synthesis_latency_ms',
        'total_nodes_found',
        'metrics',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'metrics' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
