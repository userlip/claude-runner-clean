<?php

use App\Filament\Resources\TaskSchedules\Pages\ListTaskSchedules;
use App\Models\TaskSchedule;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can view task schedules list', function () {
    $schedule = TaskSchedule::factory()->create(['user_id' => $this->user->id]);

    livewire(ListTaskSchedules::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$schedule]);
});

it('exposes a create header action', function () {
    $page = new ListTaskSchedules;

    $method = new ReflectionMethod($page, 'getHeaderActions');
    $method->setAccessible(true);

    $actions = $method->invoke($page);

    $hasCreate = collect($actions)
        ->contains(fn ($action) => $action instanceof CreateAction);

    expect($hasCreate)->toBeTrue();
});
