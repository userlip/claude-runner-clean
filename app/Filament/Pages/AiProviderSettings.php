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

    public string $kimiApiKey = '';

    public ?string $codexModel = null;

    public ?string $kimiModel = null;

    public ?int $kimiQuotaLimit = null;

    public ?int $codexQuotaLimit = null;

    public ?int $claudeQuotaLimit = null;

    public ?int $claudeContextWindow = null;

    public ?int $codexContextWindow = null;

    public ?int $kimiContextWindow = null;

    public function mount(): void
    {
        $codex = $this->getCodexProvider();
        $kimi = $this->getKimiProvider();
        $claude = $this->getClaudeProvider();

        $this->codexModel = $codex?->model;
        $this->codexQuotaLimit = $codex?->quota_limit;
        $this->codexContextWindow = $codex?->context_window;
        $this->kimiApiKey = $kimi?->api_key ?? '';
        $this->kimiModel = $kimi?->model;
        $this->kimiQuotaLimit = $kimi?->quota_limit;
        $this->kimiContextWindow = $kimi?->context_window;
        $this->claudeQuotaLimit = $claude?->quota_limit;
        $this->claudeContextWindow = $claude?->context_window;
    }

    public function getClaudeProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'claude')->first();
    }

    public function getCodexProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'codex')->first();
    }

    public function getKimiProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'kimi')->first();
    }

    public function saveClaudeSettings(): void
    {
        $claude = $this->getClaudeProvider();

        if ($claude) {
            $claude->update([
                'quota_limit' => $this->claudeQuotaLimit,
                'context_window' => $this->claudeContextWindow,
            ]);
        }

        Notification::make()
            ->title('Claude settings saved')
            ->success()
            ->send();
    }

    public function saveCodexSettings(): void
    {
        $codex = $this->getCodexProvider();

        if ($codex) {
            $codex->update([
                'model' => $this->codexModel ?: null,
                'quota_limit' => $this->codexQuotaLimit,
                'context_window' => $this->codexContextWindow,
                'is_active' => true,
            ]);
        }

        Notification::make()
            ->title('Codex settings saved')
            ->success()
            ->send();
    }

    public function saveKimiSettings(): void
    {
        $kimi = $this->getKimiProvider();

        if ($kimi) {
            $kimi->update([
                'api_key' => $this->kimiApiKey ?: null,
                'model' => $this->kimiModel ?: 'kimi-k2.5',
                'quota_limit' => $this->kimiQuotaLimit,
                'context_window' => $this->kimiContextWindow,
                'is_active' => ! empty($this->kimiApiKey),
            ]);
        }

        Notification::make()
            ->title('Kimi settings saved')
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
