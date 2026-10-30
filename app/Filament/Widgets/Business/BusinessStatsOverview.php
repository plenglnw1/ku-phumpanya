<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Business;

use App\Services\Admin\BusinessMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'ภาพรวมการใช้งาน (ไม่นับบัญชี admin)';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $metrics = app(BusinessMetrics::class);
        $summary = $metrics->summary();
        $daily = collect($metrics->searchesPerDay(14));

        return [
            Stat::make('ผู้ใช้ที่ใช้งาน 7 วัน', number_format($summary['active_7d']))
                ->description(number_format($summary['active_30d']).' คนใน 30 วัน · ผู้ใช้ทั้งหมด '.number_format($summary['users_total']))
                ->chart($daily->pluck('users')->all())
                ->color('primary'),
            Stat::make('จำนวนการค้นหา 7 วัน', number_format($summary['searches_7d']))
                ->description(number_format($summary['searches_30d']).' ครั้งใน 30 วัน')
                ->chart($daily->pluck('searches')->all())
                ->color('primary'),
            Stat::make('ค้นแล้วไม่เจอผลลัพธ์ (30 วัน)', $summary['zero_result_rate_30d'].'%')
                ->description('สัดส่วนการค้นหาที่ไม่พบเอกสารหรือกราฟ')
                ->color($summary['zero_result_rate_30d'] > 20 ? 'danger' : 'success'),
            Stat::make('เริ่มเรียนตามเส้นทาง (30 วัน)', $summary['learning_started_pct_30d'].'%')
                ->description('การค้นหาที่ผู้ใช้กดเรียนอย่างน้อย 1 การ์ด · '.number_format($summary['cards_completed_30d']).' การ์ดที่เรียนจบ'),
            Stat::make('ยินยอม PDPA ฉบับปัจจุบัน', $summary['consent_pct'].'%')
                ->description('ของผู้ใช้ทั้งหมด')
                ->color($summary['consent_pct'] < 100 ? 'warning' : 'success'),
        ];
    }
}
