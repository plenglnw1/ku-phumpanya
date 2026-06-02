<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('masked_ip')
                    ->label('ip')
                    ->searchable(query: function ($query, string $search): void {
                        $query->where('ip', 'like', "%{$search}%");
                    }),
                TextColumn::make('action')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->label('userid')
                    ->formatStateUsing(fn (?int $state): string => $state ? str_repeat('x', 7) : '—'),
                TextColumn::make('before')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state) : '—')
                    ->wrap(),
                TextColumn::make('after')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state) : '—')
                    ->wrap(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
