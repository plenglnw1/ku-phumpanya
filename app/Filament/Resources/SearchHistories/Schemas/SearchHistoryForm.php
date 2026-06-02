<?php

namespace App\Filament\Resources\SearchHistories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SearchHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Textarea::make('query')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
