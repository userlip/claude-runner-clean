<?php

use App\Filament\Pages\AiProviderSettings;
use App\Models\AiProvider;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->withoutVite();
});

test('it renders settings page', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    AiProvider::factory()->kimi()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->assertSuccessful();
});

test('it shows both providers', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    AiProvider::factory()->kimi()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->assertSee('Claude')
        ->assertSee('Kimi');
});

test('it can update kimi api key', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    $kimi = AiProvider::factory()->kimi()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->set('kimiApiKey', 'new-secret-key')
        ->call('saveKimiSettings')
        ->assertNotified();

    expect($kimi->fresh()->api_key)->toBe('new-secret-key');
});

test('it can update quota limits', function () {
    $user = User::factory()->create();
    $claude = AiProvider::factory()->claude()->create();
    AiProvider::factory()->kimi()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->set('claudeQuotaLimit', 5000000)
        ->call('saveClaudeSettings')
        ->assertNotified();

    expect($claude->fresh()->quota_limit)->toBe(5000000);
});

test('it can reset quota', function () {
    $user = User::factory()->create();
    $claude = AiProvider::factory()->claude()->create(['quota_used' => 5000000]);
    AiProvider::factory()->kimi()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->call('resetQuota', 'claude')
        ->assertNotified();

    expect($claude->fresh()->quota_used)->toBe(0);
});
