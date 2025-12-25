<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('chat')
                ->label('Open Chat')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->url(fn () => SiteChat::getUrl(['record' => $this->record]))
                ->visible(fn () => $this->record->isActive()),
        ];
    }
}
