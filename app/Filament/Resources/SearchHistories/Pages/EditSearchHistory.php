<?php

namespace App\Filament\Resources\SearchHistories\Pages;

use App\Filament\Resources\SearchHistories\SearchHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSearchHistory extends EditRecord
{
    protected static string $resource = SearchHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
