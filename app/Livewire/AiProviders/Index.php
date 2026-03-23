<?php

namespace App\Livewire\AiProviders;

use App\Models\AiProvider;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public ?string $codexModel = null;

    public ?string $kimiApiKey = '';

    public ?string $kimiModel = null;

    public ?int $claudeQuotaLimit = null;

    public ?int $claudeContextWindow = null;

    public ?int $codexQuotaLimit = null;

    public ?int $codexContextWindow = null;

    public ?int $kimiQuotaLimit = null;

    public ?int $kimiContextWindow = null;

    public function mount(): void
    {
        $claude = $this->provider('claude');
        $codex = $this->provider('codex');
        $kimi = $this->provider('kimi');

        $this->claudeQuotaLimit = $claude?->quota_limit;
        $this->claudeContextWindow = $claude?->context_window;
        $this->codexModel = $codex?->model;
        $this->codexQuotaLimit = $codex?->quota_limit;
        $this->codexContextWindow = $codex?->context_window;
        $this->kimiApiKey = $kimi?->api_key ?? '';
        $this->kimiModel = $kimi?->model;
        $this->kimiQuotaLimit = $kimi?->quota_limit;
        $this->kimiContextWindow = $kimi?->context_window;
    }

    public function saveClaude(): void
    {
        $this->provider('claude')?->update([
            'quota_limit' => $this->claudeQuotaLimit,
            'context_window' => $this->claudeContextWindow,
        ]);

        $this->success('Claude settings saved.');
    }

    public function saveCodex(): void
    {
        $this->provider('codex')?->update([
            'model' => $this->codexModel ?: null,
            'quota_limit' => $this->codexQuotaLimit,
            'context_window' => $this->codexContextWindow,
            'is_active' => true,
        ]);

        $this->success('Codex settings saved.');
    }

    public function saveKimi(): void
    {
        $this->provider('kimi')?->update([
            'api_key' => $this->kimiApiKey ?: null,
            'model' => $this->kimiModel ?: 'kimi-k2.5',
            'quota_limit' => $this->kimiQuotaLimit,
            'context_window' => $this->kimiContextWindow,
            'is_active' => ! empty($this->kimiApiKey),
        ]);

        $this->success('Kimi settings saved.');
    }

    public function resetQuota(string $name): void
    {
        $provider = $this->provider($name);

        if ($provider) {
            $provider->update([
                'quota_used' => 0,
                'quota_resets_at' => $provider->calculateNextReset(),
            ]);
        }

        $this->success(ucfirst($name).' quota reset.');
    }

    protected function provider(string $name): ?AiProvider
    {
        return AiProvider::where('name', $name)->first();
    }

    public function render(): View
    {
        return view('livewire.ai-providers.index', [
            'claude' => $this->provider('claude'),
            'codex' => $this->provider('codex'),
            'kimi' => $this->provider('kimi'),
        ]);
    }
}
