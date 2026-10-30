<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Business;

use App\Services\Admin\BusinessMetrics;
use Filament\Widgets\ChartWidget;

class SearchesByFacultyChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'การค้นหาตามคณะ 10 อันดับ (30 วัน)';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $faculties = app(BusinessMetrics::class)->searchesByFaculty(30, 10);

        return [
            'datasets' => [
                [
                    'label' => 'การค้นหา',
                    'data' => array_values($faculties),
                    'backgroundColor' => '#b45309',
                ],
            ],
            'labels' => array_keys($faculties),
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
