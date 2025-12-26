<?php

namespace App\Filament\Pages;

use App\Models\AiProvider;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class AiProviderSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'AI Providers';

    protected static ?string $title = 'AI Provider Settings';

    protected static ?string $slug = 'ai-provider-settings';

    protected string $view = 'filament.pages.ai-provider-settings';

    public string $glmApiKey = '';

    public ?int $glmQuotaLimit = null;

    public ?int $claudeQuotaLimit = null;

    public function mount(): void
    {
        $glm = $this->getGlmProvider();
        $claude = $this->getClaudeProvider();

        $this->glmApiKey = $glm?->api_key ?? '';
        $this->glmQuotaLimit = $glm?->quota_limit;
        $this->claudeQuotaLimit = $claude?->quota_limit;
    }

    public function getClaudeProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'claude')->first();
    }

    public function getGlmProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'glm')->first();
    }

    public function saveClaudeSettings(): void
    {
        $claude = $this->getClaudeProvider();

        if ($claude) {
            $claude->update([
                'quota_limit' => $this->claudeQuotaLimit,
            ]);
        }

        Notification::make()
            ->title('Claude settings saved')
            ->success()
            ->send();
    }

    public function saveGlmSettings(): void
    {
        $glm = $this->getGlmProvider();

        if ($glm) {
            $glm->update([
                'api_key' => $this->glmApiKey ?: null,
                'quota_limit' => $this->glmQuotaLimit,
                'is_active' => ! empty($this->glmApiKey),
            ]);
        }

        Notification::make()
            ->title('GLM settings saved')
            ->success()
            ->send();
    }

    public function resetQuota(string $providerName): void
    {
        $provider = AiProvider::where('name', $providerName)->first();

        if ($provider) {
            $provider->update([
                'quota_used' => 0,
                'quota_resets_at' => $provider->calculateNextReset(),
            ]);
        }

        Notification::make()
            ->title('Quota reset')
            ->success()
            ->send();
    }
}
