<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityLogFactory> */
    use HasFactory;
    protected $fillable = [
        'ip',
        'action',
        'user_id',
        'before',
        'after',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMaskedIpAttribute(): string
    {
        $parts = explode('.', $this->ip);

        if (count($parts) === 4) {
            return $parts[0].'.x.x.x';
        }

        return $this->ip;
    }
}
