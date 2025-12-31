<?php

namespace App\Filament\Resources\ProposalResource\Pages;

use App\Filament\Resources\ProposalResource;
use App\Services\TelegramService;
use Filament\Resources\Pages\CreateRecord;

class CreateProposal extends CreateRecord
{
    protected static string $resource = ProposalResource::class;

    protected function afterCreate(): void
    {
        try {
            app(TelegramService::class)->sendProposalNotification($this->record);
        } catch (\Exception $e) {
            // Telegram notification is optional
        }
    }
}
