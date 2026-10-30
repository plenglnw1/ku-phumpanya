<?php

declare(strict_types=1);

namespace App\Filament\Resources\SearchHistories\Tables;

use App\Filament\Pages\KnowledgeGraphExplorer;
use App\Models\SearchHistory;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SearchHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('query')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'zero_results' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('total_latency_ms')
                    ->label('Latency (ms)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                Action::make('graph')
                    ->label('ดูกราฟ')
                    ->icon(Heroicon::OutlinedShare)
                    ->url(fn (SearchHistory $record): string => KnowledgeGraphExplorer::getUrl(['history' => $record->id])),
            ]);
    }
}
