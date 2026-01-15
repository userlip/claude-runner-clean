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

    public string $minimaxApiKey = '';

    public ?string $codexModel = null;

    public ?int $glmQuotaLimit = null;

    public ?int $minimaxQuotaLimit = null;

    public ?int $codexQuotaLimit = null;

    public ?int $claudeQuotaLimit = null;

    public ?int $claudeContextWindow = null;

    public ?int $codexContextWindow = null;

    public ?int $glmContextWindow = null;

    public ?int $minimaxContextWindow = null;

    public function mount(): void
    {
        $codex = $this->getCodexProvider();
        $glm = $this->getGlmProvider();
        $minimax = $this->getMinimaxProvider();
        $claude = $this->getClaudeProvider();

        $this->codexModel = $codex?->model;
        $this->codexQuotaLimit = $codex?->quota_limit;
        $this->codexContextWindow = $codex?->context_window;
        $this->glmApiKey = $glm?->api_key ?? '';
        $this->glmQuotaLimit = $glm?->quota_limit;
        $this->glmContextWindow = $glm?->context_window;
        $this->minimaxApiKey = $minimax?->api_key ?? '';
        $this->minimaxQuotaLimit = $minimax?->quota_limit;
        $this->minimaxContextWindow = $minimax?->context_window;
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

    public function getGlmProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'glm')->first();
    }

    public function getMinimaxProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'minimax')->first();
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

    public function saveGlmSettings(): void
    {
        $glm = $this->getGlmProvider();

        if ($glm) {
            $glm->update([
                'api_key' => $this->glmApiKey ?: null,
                'quota_limit' => $this->glmQuotaLimit,
                'context_window' => $this->glmContextWindow,
                'is_active' => ! empty($this->glmApiKey),
            ]);
        }

        Notification::make()
            ->title('GLM settings saved')
            ->success()
            ->send();
    }

    public function saveMinimaxSettings(): void
    {
        $minimax = $this->getMinimaxProvider();

        if ($minimax) {
            $minimax->update([
                'api_key' => $this->minimaxApiKey ?: null,
                'quota_limit' => $this->minimaxQuotaLimit,
                'context_window' => $this->minimaxContextWindow,
                'is_active' => ! empty($this->minimaxApiKey),
            ]);
        }

        Notification::make()
            ->title('Minimax settings saved')
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
