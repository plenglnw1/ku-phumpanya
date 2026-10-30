<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Business;

use App\Services\Admin\BusinessMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SearchesPerDayChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'การค้นหาและผู้ใช้ต่อวัน (30 วัน)';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $days = collect(app(BusinessMetrics::class)->searchesPerDay(30));

        return [
            'datasets' => [
                [
                    'label' => 'การค้นหา',
                    'data' => $days->pluck('searches')->all(),
                    'borderColor' => '#2D5A43',
                    'backgroundColor' => 'rgba(45, 90, 67, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'ผู้ใช้',
                    'data' => $days->pluck('users')->all(),
                    'borderColor' => '#b45309',
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->pluck('date')->map(fn (string $d): string => CarbonImmutable::parse($d)->format('d/m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
