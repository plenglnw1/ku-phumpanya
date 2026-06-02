<?php

namespace App\Filament\Resources\SearchHistories\Pages;

use App\Filament\Resources\SearchHistories\SearchHistoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSearchHistory extends ViewRecord
{
    protected static string $resource = SearchHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
