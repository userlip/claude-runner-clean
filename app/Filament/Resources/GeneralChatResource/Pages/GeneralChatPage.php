<?php

namespace App\Filament\Resources\GeneralChatResource\Pages;

use App\Filament\Resources\GeneralChatResource;
use App\Models\GeneralChat;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class GeneralChatPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GeneralChatResource::class;

    protected string $view = 'filament.resources.general-chat-resource.pages.general-chat-page';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        /** @var GeneralChat $record */
        $record = $this->getRecord();

        return $record->title ?? 'General Chat';
    }

    public function getSubheading(): ?string
    {
        /** @var GeneralChat $record */
        $record = $this->getRecord();

        return $record->working_directory;
    }
}
