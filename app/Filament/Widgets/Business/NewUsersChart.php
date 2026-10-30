<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Business;

use App\Services\Admin\BusinessMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class NewUsersChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'ผู้ใช้ใหม่ต่อสัปดาห์ (8 สัปดาห์)';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $weeks = collect(app(BusinessMetrics::class)->newUsersPerWeek(8));

        return [
            'datasets' => [
                [
                    'label' => 'ผู้ใช้ใหม่',
                    'data' => $weeks->pluck('users')->all(),
                    'backgroundColor' => '#2D5A43',
                ],
            ],
            'labels' => $weeks->pluck('week')->map(fn (string $w): string => CarbonImmutable::parse($w)->format('d/m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
