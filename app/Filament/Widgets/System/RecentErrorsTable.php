<?php

declare(strict_types=1);

namespace App\Filament\Widgets\System;

use App\Filament\Resources\SearchHistories\SearchHistoryResource;
use App\Models\SearchHistory;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentErrorsTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'ข้อผิดพลาดล่าสุด';

    public function table(Table $table): Table
    {
        return $table
            ->query(SearchHistory::query()->where('status', 'error')->latest())
            ->columns([
                TextColumn::make('created_at')->label('เวลา')->dateTime('d/m/Y H:i'),
                TextColumn::make('query')->label('คำค้น')->limit(60),
                TextColumn::make('metrics.error_message')->label('ข้อความ error')->limit(120)->wrap(),
                TextColumn::make('total_latency_ms')->label('ms')->numeric(),
            ])
            ->recordUrl(fn (SearchHistory $record): string => SearchHistoryResource::getUrl('view', ['record' => $record]))
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('ไม่มีข้อผิดพลาด');
    }
}
