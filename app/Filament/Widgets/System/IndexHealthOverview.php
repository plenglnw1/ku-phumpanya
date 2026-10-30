<?php

declare(strict_types=1);

namespace App\Filament\Widgets\System;

use App\Services\Admin\SystemMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class IndexHealthOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Elasticsearch index';

    protected ?string $description = 'อัปเดตทุก 5 นาที';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $es = app(SystemMetrics::class)->elasticsearch();
        $sourceLabels = (array) config('knowledge_graph.source_labels');

        if (! $es['enabled']) {
            return [Stat::make('Elasticsearch', 'ปิดอยู่')->color('gray')];
        }

        if ($es['error'] !== null) {
            return [Stat::make('Elasticsearch', 'เชื่อมต่อไม่ได้')->description($es['error'])->color('danger')];
        }

        $docs = $es['indices']['docs'] ?? null;
        $relations = $es['indices']['relations'] ?? null;

        $stats = [
            Stat::make('Cluster health', strtoupper((string) $es['health']))
                ->description($es['health'] === 'yellow' ? 'yellow ปกติสำหรับเครื่องเดียว (ไม่มี replica)' : 'สถานะ cluster')
                ->color(match ($es['health']) {
                    'green', 'yellow' => 'success',
                    default => 'danger',
                }),
        ];

        if ($docs !== null) {
            $stats[] = Stat::make('เอกสาร', number_format($docs['docs']))
                ->description(Number::fileSize($docs['size_bytes']).' · สร้าง index '.$this->date($docs['created_at']));
        }

        if ($relations !== null) {
            $stats[] = Stat::make('ความสัมพันธ์ (relations)', number_format($relations['docs']))
                ->description(Number::fileSize($relations['size_bytes']).' · สร้าง index '.$this->date($relations['created_at']));
        }

        $stats[] = Stat::make('เอกสารตามแหล่ง', (string) count($es['sources']).' แหล่ง')
            ->description(collect($es['sources'])
                ->map(fn (int $count, string $source): string => ($sourceLabels[$source] ?? $source).' '.number_format($count))
                ->implode(' · '));

        return $stats;
    }

    private function date(?string $value): string
    {
        return $value === null ? '-' : CarbonImmutable::parse($value)->format('d/m/Y');
    }
}
