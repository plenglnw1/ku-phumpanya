<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Business;

use App\Services\Admin\BusinessMetrics;
use Filament\Widgets\ChartWidget;

class TopConceptsChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'หัวข้อที่ผู้ใช้สนใจ 10 อันดับ (30 วัน)';

    protected ?string $description = 'นับจากแนวคิดหลักในกราฟความรู้ที่ผู้ใช้ได้รับ';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $concepts = app(BusinessMetrics::class)->topConcepts(30, 10);

        return [
            'datasets' => [
                [
                    'label' => 'จำนวนครั้ง',
                    'data' => array_values($concepts),
                    'backgroundColor' => '#2D5A43',
                ],
            ],
            'labels' => array_map('strval', array_keys($concepts)),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
