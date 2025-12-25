<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Services\PloiService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncSites')
                ->label('Sync from Ploi')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    try {
                        $service = new PloiService;
                        $count = $service->syncSites();

                        Notification::make()
                            ->title('Sites Synced')
                            ->body("Successfully synced {$count} site(s) from Ploi.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
