<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Business;

use App\Services\Admin\BusinessMetrics;
use Filament\Widgets\ChartWidget;

class SearchesByRoleChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'การค้นหาตามบทบาท (30 วัน)';

    protected ?string $maxHeight = '260px';

    private const ROLE_LABELS = [
        'student' => 'นิสิต',
        'teacher' => 'อาจารย์',
        'researcher' => 'นักวิจัย',
    ];

    protected function getData(): array
    {
        $roles = app(BusinessMetrics::class)->searchesByRole(30);

        return [
            'datasets' => [
                [
                    'data' => array_values($roles),
                    'backgroundColor' => ['#2D5A43', '#b45309', '#7c3aed', '#0e7490', '#94a3b8'],
                ],
            ],
            'labels' => array_map(fn (string $role): string => self::ROLE_LABELS[$role] ?? $role, array_keys($roles)),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
