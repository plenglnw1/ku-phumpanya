<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\System\GeminiTokensChart;
use App\Filament\Widgets\System\IndexHealthOverview;
use App\Filament\Widgets\System\LatencyChart;
use App\Filament\Widgets\System\RecentErrorsTable;
use App\Filament\Widgets\System\SystemStatsOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class SystemHealth extends BaseDashboard
{
    protected static string $routePath = '/system';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'System';

    protected static ?string $title = 'System health';

    public function getWidgets(): array
    {
        return [
            SystemStatsOverview::class,
            IndexHealthOverview::class,
            LatencyChart::class,
            GeminiTokensChart::class,
            RecentErrorsTable::class,
        ];
    }
}
