<?php

namespace App\Filament\Resources\SearchHistories;

use App\Filament\Resources\SearchHistories\Pages\ListSearchHistories;
use App\Filament\Resources\SearchHistories\Pages\ViewSearchHistory;
use App\Filament\Resources\SearchHistories\Schemas\SearchHistoryForm;
use App\Filament\Resources\SearchHistories\Schemas\SearchHistoryInfolist;
use App\Filament\Resources\SearchHistories\Tables\SearchHistoriesTable;
use App\Models\SearchHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SearchHistoryResource extends Resource
{
    protected static ?string $model = SearchHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'Search History';

    protected static ?string $modelLabel = 'Search History';

    protected static ?string $pluralModelLabel = 'Search Histories';

    public static function form(Schema $schema): Schema
    {
        return SearchHistoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SearchHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SearchHistoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSearchHistories::route('/'),
            'view' => ViewSearchHistory::route('/{record}'),
        ];
    }
}
