<?php

use App\Filament\Widgets\AiProviderQuotaWidget;
use App\Models\AiProvider;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('it renders widget', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();

    Livewire::actingAs($user)
        ->test(AiProviderQuotaWidget::class)
        ->assertSuccessful();
});

test('it shows provider usage', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create([
        'quota_used' => 5000000,
        'quota_limit' => 10000000,
    ]);

    Livewire::actingAs($user)
        ->test(AiProviderQuotaWidget::class)
        ->assertSee('Claude')
        ->assertSee('50');
});

test('it shows multiple providers', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    AiProvider::factory()->kimi()->create(['is_active' => true]);

    Livewire::actingAs($user)
        ->test(AiProviderQuotaWidget::class)
        ->assertSee('Claude')
        ->assertSee('Kimi');
});
