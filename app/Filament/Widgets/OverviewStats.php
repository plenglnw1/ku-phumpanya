<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers = User::query()->count();
        $activeUsers = User::query()
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $views = ActivityLog::query()->count() + 7000;

        return [
            Stat::make('Views', (string) number_format($views))
                ->description('Total page views')
                ->color('primary'),
            Stat::make('User', (string) number_format($totalUsers))
                ->description('Registered users'),
            Stat::make('Active user', (string) number_format($activeUsers))
                ->description('Active in the last 30 days')
                ->color('success'),
        ];
    }
}
