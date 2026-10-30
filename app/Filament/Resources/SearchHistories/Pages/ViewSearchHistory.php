<?php

namespace App\Filament\Resources\SearchHistories\Pages;

use App\Filament\Pages\KnowledgeGraphExplorer;
use App\Filament\Resources\SearchHistories\SearchHistoryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSearchHistory extends ViewRecord
{
    protected static string $resource = SearchHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('graph')
                ->label('ดูกราฟ')
                ->icon(Heroicon::OutlinedShare)
                ->url(fn (): string => KnowledgeGraphExplorer::getUrl(['history' => $this->getRecord()->getKey()])),
        ];
    }
}
