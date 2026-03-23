<?php

namespace App\Livewire\AiProviders;

use App\Models\AiProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    // New provider form
    public string $newName = '';

    public string $newDisplayName = '';

    public string $newBaseUrl = '';

    public string $newApiKey = '';

    public string $newModel = '';

    public ?int $newContextWindow = null;

    public bool $showAddForm = false;

    public function saveProvider(int $id): void
    {
        $data = $this->only([
            "provider_{$id}_display_name",
            "provider_{$id}_base_url",
            "provider_{$id}_api_key",
            "provider_{$id}_model",
            "provider_{$id}_context_window",
            "provider_{$id}_quota_limit",
            "provider_{$id}_is_active",
        ]);

        $provider = AiProvider::findOrFail($id);

        $updateData = [
            'display_name' => $data["provider_{$id}_display_name"] ?? $provider->display_name,
            'model' => $data["provider_{$id}_model"] ?: null,
            'context_window' => $data["provider_{$id}_context_window"] ?: null,
            'quota_limit' => $data["provider_{$id}_quota_limit"] ?: null,
            'is_active' => (bool) ($data["provider_{$id}_is_active"] ?? $provider->is_active),
        ];

        // Only update base_url and api_key for non-builtin providers
        if (! $provider->isClaude() && ! $provider->isCodex()) {
            $updateData['base_url'] = $data["provider_{$id}_base_url"] ?: null;
            $apiKey = $data["provider_{$id}_api_key"] ?? '';
            if ($apiKey !== '' && $apiKey !== '********') {
                $updateData['api_key'] = $apiKey;
            }
        }

        $provider->update($updateData);

        $this->success($provider->display_name.' saved.');
    }

    public function resetQuota(int $id): void
    {
        $provider = AiProvider::findOrFail($id);

        $provider->update([
            'quota_used' => 0,
            'quota_resets_at' => $provider->calculateNextReset(),
        ]);

        $this->success($provider->display_name.' quota reset.');
    }

    public function setDefault(int $id): void
    {
        AiProvider::where('is_default', true)->update(['is_default' => false]);
        AiProvider::where('id', $id)->update(['is_default' => true]);

        $this->success('Default provider updated.');
    }

    public function addProvider(): void
    {
        $this->validate([
            'newDisplayName' => 'required|string|max:100',
            'newBaseUrl' => 'required|url|max:500',
            'newApiKey' => 'required|string|max:500',
            'newModel' => 'required|string|max:100',
        ]);

        AiProvider::create([
            'name' => Str::slug($this->newDisplayName),
            'display_name' => $this->newDisplayName,
            'base_url' => $this->newBaseUrl,
            'api_key' => $this->newApiKey,
            'model' => $this->newModel,
            'context_window' => $this->newContextWindow ?: 200000,
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->newDisplayName = '';
        $this->newBaseUrl = '';
        $this->newApiKey = '';
        $this->newModel = '';
        $this->newContextWindow = null;
        $this->showAddForm = false;

        $this->success('Provider added.');
    }

    public function deleteProvider(int $id): void
    {
        $provider = AiProvider::findOrFail($id);

        if ($provider->isClaude() || $provider->isCodex()) {
            $this->error('Cannot delete built-in providers.');

            return;
        }

        $provider->delete();
        $this->success($provider->display_name.' deleted.');
    }

    public function render(): View
    {
        $providers = AiProvider::orderByDesc('is_default')->orderBy('name')->get();

        // Hydrate dynamic properties for each provider
        foreach ($providers as $provider) {
            $key = "provider_{$provider->id}";
            if (! isset($this->{"{$key}_display_name"})) {
                $this->addDynamicProperties($provider);
            }
        }

        return view('livewire.ai-providers.index', [
            'providers' => $providers,
        ]);
    }

    protected function addDynamicProperties(AiProvider $provider): void
    {
        $id = $provider->id;
        $isBuiltin = $provider->isClaude() || $provider->isCodex();

        // Use __set for dynamic Livewire properties
        $this->{"provider_{$id}_display_name"} = $provider->display_name;
        $this->{"provider_{$id}_base_url"} = $isBuiltin ? '' : ($provider->base_url ?? '');
        $this->{"provider_{$id}_api_key"} = $isBuiltin ? '' : ($provider->api_key ? '********' : '');
        $this->{"provider_{$id}_model"} = $provider->model ?? '';
        $this->{"provider_{$id}_context_window"} = $provider->context_window;
        $this->{"provider_{$id}_quota_limit"} = $provider->quota_limit;
        $this->{"provider_{$id}_is_active"} = $provider->is_active;
    }
}
