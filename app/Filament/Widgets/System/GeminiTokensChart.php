<?php

declare(strict_types=1);

namespace App\Filament\Widgets\System;

use App\Services\Admin\SystemMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class GeminiTokensChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Gemini tokens ต่อวัน (14 วัน)';

    protected ?string $description = 'นับเฉพาะการค้นหาหลังเริ่มบันทึก usage';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $days = collect(app(SystemMetrics::class)->gemini(14)['per_day']);

        return [
            'datasets' => [
                [
                    'label' => 'tokens',
                    'data' => $days->pluck('tokens')->all(),
                    'backgroundColor' => '#7c3aed',
                ],
            ],
            'labels' => $days->pluck('date')->map(fn (string $d): string => CarbonImmutable::parse($d)->format('d/m'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
