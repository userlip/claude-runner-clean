<?php

namespace App\Filament\Widgets;

use App\Models\AiProvider;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class AiProviderQuotaWidget extends Widget
{
    protected string $view = 'filament.widgets.ai-provider-quota-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, AiProvider>
     */
    public function getProviders(): Collection
    {
        return AiProvider::where('is_active', true)->get();
    }
}
