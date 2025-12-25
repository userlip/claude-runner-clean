<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Models\Site;
use Filament\Resources\Pages\Page;

class SiteChat extends Page
{
    protected static string $resource = SiteResource::class;

    protected string $view = 'filament.resources.site-resource.pages.site-chat';

    public Site $record;

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless($this->record->isActive(), 403, 'Site is not active');
    }

    public function getTitle(): string
    {
        return "Chat - {$this->record->domain}";
    }
}
