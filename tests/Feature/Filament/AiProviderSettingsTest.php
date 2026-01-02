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
    AiProvider::factory()->glm()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->assertSuccessful();
});

test('it shows both providers', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    AiProvider::factory()->glm()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->assertSee('Claude')
        ->assertSee('GLM (z.ai)');
});

test('it can update glm api key', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    $glm = AiProvider::factory()->glm()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->set('glmApiKey', 'new-secret-key')
        ->call('saveGlmSettings')
        ->assertNotified();

    expect($glm->fresh()->api_key)->toBe('new-secret-key');
});

test('it can update quota limits', function () {
    $user = User::factory()->create();
    $claude = AiProvider::factory()->claude()->create();
    AiProvider::factory()->glm()->create();

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
    AiProvider::factory()->glm()->create();

    Livewire::actingAs($user)
        ->test(AiProviderSettings::class)
        ->call('resetQuota', 'claude')
        ->assertNotified();

    expect($claude->fresh()->quota_used)->toBe(0);
});
