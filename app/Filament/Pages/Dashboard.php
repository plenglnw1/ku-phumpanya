<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\Business\BusinessStatsOverview;
use App\Filament\Widgets\Business\NewUsersChart;
use App\Filament\Widgets\Business\SearchesByFacultyChart;
use App\Filament\Widgets\Business\SearchesByRoleChart;
use App\Filament\Widgets\Business\SearchesPerDayChart;
use App\Filament\Widgets\Business\TopConceptsChart;
use App\Filament\Widgets\Business\TopQueriesTable;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Business';

    protected static ?string $title = 'Business dashboard';

    public function getWidgets(): array
    {
        return [
            BusinessStatsOverview::class,
            SearchesPerDayChart::class,
            NewUsersChart::class,
            SearchesByRoleChart::class,
            SearchesByFacultyChart::class,
            TopConceptsChart::class,
            TopQueriesTable::class,
        ];
    }
}
