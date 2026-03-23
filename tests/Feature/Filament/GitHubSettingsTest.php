<?php

use App\Filament\Pages\GitHubSettings;
use App\Models\Connection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('can view github settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(GitHubSettings::class)
        ->assertSuccessful()
        ->assertSee('Not Connected');
});

test('shows connected status when github is connected', function () {
    $user = User::factory()->create();
    Connection::factory()->github()->create([
        'user_id' => $user->id,
        'metadata' => [
            'github_username' => 'testuser123',
        ],
    ]);

    $this->actingAs($user);

    Livewire::test(GitHubSettings::class)
        ->assertSuccessful()
        ->assertSee('Connected as testuser123');
});

test('can disconnect github', function () {
    $user = User::factory()->create();
    Connection::factory()->github()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(GitHubSettings::class)
        ->callAction('disconnect');

    expect($user->fresh()->githubConnection)->toBeNull();
});
