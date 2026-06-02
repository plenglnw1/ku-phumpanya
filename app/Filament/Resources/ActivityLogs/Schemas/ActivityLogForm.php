<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ActivityLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ip')
                    ->required(),
                TextInput::make('action')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                Textarea::make('before')
                    ->columnSpanFull(),
                Textarea::make('after')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('success'),
            ]);
    }
}
