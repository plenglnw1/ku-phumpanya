<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class AdminPeriod
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly Carbon $prevFrom,
        public readonly Carbon $prevTo,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $period = (string) $request->query('period', '7d');

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse((string) $request->query('from'))->startOfDay();
            $to = Carbon::parse((string) $request->query('to'))->endOfDay();

            if ($from->greaterThan($to)) {
                throw new InvalidArgumentException('from must be before to');
            }

            $days = max(1, (int) $from->diffInDays($to) + 1);

            return new self(
                from: $from,
                to: $to,
                prevFrom: $from->copy()->subDays($days),
                prevTo: $from->copy()->subSecond(),
            );
        }

        $days = match ($period) {
            '30d' => 30,
            default => 7,
        };

        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();

        return new self(
            from: $from,
            to: $to,
            prevFrom: $from->copy()->subDays($days),
            prevTo: $from->copy()->subSecond(),
        );
    }
}
