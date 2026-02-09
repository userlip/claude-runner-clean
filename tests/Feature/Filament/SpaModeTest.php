<?php

use App\Models\User;
use Filament\Facades\Filament;

test('admin panel enables spa mode when authenticated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);

    expect($panel->hasSpaMode())->toBeTrue();
    expect($panel->hasSpaPrefetching())->toBeTrue();
});

test('admin panel disables spa mode for guests', function () {
    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);

    expect($panel->hasSpaMode())->toBeFalse();
});
