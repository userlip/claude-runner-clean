<?php

use App\Filament\Resources\TaskSchedules\Pages\CreateTaskSchedule;
use App\Filament\Resources\TaskSchedules\Pages\ListTaskSchedules;
use App\Models\AiProvider;
use App\Models\Repository;
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

it('can create a task schedule', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $provider = AiProvider::factory()->create(['is_active' => true]);

    livewire(CreateTaskSchedule::class)
        ->fillForm([
            'name' => 'Nightly checks',
            'repository_id' => $repository->id,
            'user_id' => $this->user->id,
            'ai_provider_id' => $provider->id,
            'prompt' => 'Run scheduled tests',
            'cron_expression' => '0 * * * *',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TaskSchedule::where('name', 'Nightly checks')->exists())->toBeTrue();
});
