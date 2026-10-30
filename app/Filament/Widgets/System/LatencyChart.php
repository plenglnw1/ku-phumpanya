<?php

declare(strict_types=1);

namespace App\Filament\Widgets\System;

use App\Services\Admin\SystemMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class LatencyChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Latency รวมต่อวัน (วินาที, 14 วัน)';

    protected ?string $description = 'เส้นประคือเป้า 3 วินาที (gate 1.3.1)';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $days = collect(app(SystemMetrics::class)->latencyPerDay(14));
        $seconds = fn (?int $ms): ?float => $ms === null ? null : round($ms / 1000, 2);

        return [
            'datasets' => [
                [
                    'label' => 'p50',
                    'data' => $days->pluck('p50')->map($seconds)->all(),
                    'borderColor' => '#2D5A43',
                    'spanGaps' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'p95',
                    'data' => $days->pluck('p95')->map($seconds)->all(),
                    'borderColor' => '#b45309',
                    'spanGaps' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'เป้า 3 s',
                    'data' => $days->map(fn (): float => 3.0)->all(),
                    'borderColor' => '#94a3b8',
                    'borderDash' => [6, 6],
                    'pointRadius' => 0,
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
