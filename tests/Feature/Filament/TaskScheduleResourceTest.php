<?php

use App\Filament\Resources\TaskSchedules\Pages\ListTaskSchedules;
use App\Models\TaskSchedule;
use App\Models\User;
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
