<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Business;

use App\Services\Admin\BusinessMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopQueriesTable extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'คำค้นยอดนิยม 10 อันดับ (30 วัน)';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(app(BusinessMetrics::class)->topQueries(30, 10))
                ->mapWithKeys(fn (array $row, int $i): array => [(string) ($i + 1) => $row])
                ->all())
            ->columns([
                TextColumn::make('query')->label('คำค้น')->wrap(),
                TextColumn::make('count')->label('จำนวนครั้ง')->numeric(),
                TextColumn::make('zero_results')
                    ->label('ไม่พบผลลัพธ์')
                    ->numeric()
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
            ])
            ->paginated(false);
    }
}
