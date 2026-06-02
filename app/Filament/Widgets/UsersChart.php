<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class UsersChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Total Users';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
        $base = max(User::query()->count(), 1);

        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => [
                        $base * 8,
                        $base * 10,
                        $base * 12,
                        $base * 15,
                        $base * 22,
                        $base * 18,
                        $base * 20,
                    ],
                    'borderColor' => '#374151',
                    'backgroundColor' => 'rgba(55, 65, 81, 0.08)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Trend',
                    'data' => [
                        $base * 6,
                        $base * 8,
                        $base * 11,
                        $base * 13,
                        $base * 16,
                        $base * 19,
                        $base * 24,
                    ],
                    'borderColor' => '#60a5fa',
                    'borderDash' => [6, 6],
                    'fill' => false,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
