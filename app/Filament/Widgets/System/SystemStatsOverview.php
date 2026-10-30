<?php

declare(strict_types=1);

namespace App\Filament\Widgets\System;

use App\Services\Admin\SystemMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'สุขภาพระบบ (7 วันล่าสุด)';

    /** Gate 1.3.1: first paint within 3 s. */
    private const LATENCY_TARGET_MS = 3000;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $system = app(SystemMetrics::class);
        $latency = $system->latency(7);
        $errors = $system->errors(7);
        $gemini = $system->gemini(7);
        $links = $system->linkCheck();
        $recall = $system->recall();

        $total = $latency['total'];

        return [
            Stat::make('Latency p50 / p95', $this->ms($total['p50']).' / '.$this->ms($total['p95']))
                ->description(sprintf(
                    'ค้นข้อมูล %s / %s · สรุปผล %s / %s · %d ครั้ง',
                    $this->ms($latency['retrieval']['p50']),
                    $this->ms($latency['retrieval']['p95']),
                    $this->ms($latency['synthesis']['p50']),
                    $this->ms($latency['synthesis']['p95']),
                    $total['samples'],
                ))
                ->color($total['p95'] === null ? 'gray' : ($total['p95'] <= self::LATENCY_TARGET_MS ? 'success' : 'warning')),
            Stat::make('Error rate', $errors['rate'].'%')
                ->description(number_format($errors['errors']).' จาก '.number_format($errors['total']).' การค้นหา')
                ->color($errors['rate'] > 5 ? 'danger' : 'success'),
            Stat::make('Gemini tokens', number_format($gemini['total_tokens']))
                ->description(sprintf(
                    'เฉลี่ย %s ต่อการค้นหา · %d calls · 429 = %d · fallback = %d',
                    $gemini['avg_tokens'] === null ? '-' : number_format($gemini['avg_tokens']),
                    $gemini['calls'],
                    $gemini['rate_limited'],
                    $gemini['fallbacks'],
                ))
                ->color($gemini['rate_limited'] > 0 ? 'warning' : 'primary'),
            Stat::make('Recall@10 (offline)', $recall === null ? 'ยังไม่เคยรัน' : round($recall['recall'] * 100, 1).'%')
                ->description($recall === null
                    ? 'รัน php artisan eval:retrieval'
                    : 'รันล่าสุด '.CarbonImmutable::parse($recall['ran_at'])->format('d/m/Y H:i'))
                ->color($recall === null ? 'gray' : ($recall['recall'] >= 0.7 ? 'success' : 'warning')),
            Stat::make('ลิงก์ต้นฉบับใช้งานได้', $links === null ? 'ยังไม่มีรายงาน' : $links['percent'].'%')
                ->description($links === null
                    ? 'รัน php artisan links:check'
                    : $links['ok'].' / '.$links['checked'].' ลิงก์ · ตรวจ '.CarbonImmutable::parse($links['checked_at'])->format('d/m/Y'))
                ->color($links === null ? 'gray' : ($links['percent'] >= 95 ? 'success' : 'warning')),
        ];
    }

    private function ms(?int $value): string
    {
        return $value === null ? '-' : number_format($value / 1000, 1).' s';
    }
}
