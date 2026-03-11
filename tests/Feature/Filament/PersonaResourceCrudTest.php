<?php

use App\Enums\PersonaStatus;
use App\Filament\Resources\Personas\Pages\CreatePersona;
use App\Filament\Resources\Personas\Pages\EditPersona;
use App\Filament\Resources\Personas\Pages\ListPersonas;
use App\Models\Persona;
use App\Models\Repository;
use App\Models\User;
use App\Services\PersonaStorageService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->repository = Repository::factory()->create(['user_id' => $this->user->id]);
});

afterEach(function () {
    // Clean up persona storage directories created during tests
    $basePath = storage_path('personas');
    if (File::isDirectory($basePath)) {
        foreach (File::directories($basePath) as $dir) {
            File::deleteDirectory($dir);
        }
    }
});

test('creating a persona via filament creates storage directory and prompt.md', function () {
    $masterPrompt = 'Analyze the codebase for security vulnerabilities and provide detailed reports.';

    livewire(CreatePersona::class)
        ->fillForm([
            'name' => 'Security Auditor',
            'description' => 'Automated security analysis persona',
            'master_prompt' => $masterPrompt,
            'repository_id' => $this->repository->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $persona = Persona::where('name', 'Security Auditor')->first();

    expect($persona)->not->toBeNull();
    expect($persona->user_id)->toBe($this->user->id);
    expect($persona->repository_id)->toBe($this->repository->id);
    expect($persona->master_prompt)->toBe($masterPrompt);
    expect($persona->is_active)->toBeTrue();

    // Verify storage directory structure was created by observer
    $storagePath = $persona->getStoragePath();
    expect(File::isDirectory($storagePath))->toBeTrue();
    expect(File::isDirectory("{$storagePath}/history"))->toBeTrue();
    expect(File::isDirectory("{$storagePath}/completed-plans"))->toBeTrue();
    expect(File::exists("{$storagePath}/context.md"))->toBeTrue();

    // Verify prompt.md was created with master_prompt content
    expect(File::exists("{$storagePath}/prompt.md"))->toBeTrue();
    expect(File::get("{$storagePath}/prompt.md"))->toBe($masterPrompt);
});

test('editing master_prompt updates prompt.md file', function () {
    $persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'master_prompt' => 'Original prompt content',
    ]);

    $updatedPrompt = 'Updated prompt: Focus on performance optimization and caching strategies.';

    livewire(EditPersona::class, ['record' => $persona->getRouteKey()])
        ->fillForm([
            'master_prompt' => $updatedPrompt,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Verify the prompt.md file was updated via observer
    $storageService = app(PersonaStorageService::class);
    $promptContent = $storageService->readFile($persona->fresh(), 'prompt.md');

    expect($promptContent)->toBe($updatedPrompt);
});

test('activate and deactivate toggle works on list page', function () {
    $persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
        'status' => PersonaStatus::Active,
    ]);

    // Deactivate
    livewire(ListPersonas::class)
        ->callTableAction('toggle_active', $persona)
        ->assertNotified('Persona deactivated');

    $persona->refresh();
    expect($persona->is_active)->toBeFalse();
    expect($persona->status)->toBe(PersonaStatus::Paused);

    // Activate
    livewire(ListPersonas::class)
        ->callTableAction('toggle_active', $persona)
        ->assertNotified('Persona activated');

    $persona->refresh();
    expect($persona->is_active)->toBeTrue();
    expect($persona->status)->toBe(PersonaStatus::Active);
});

test('activate and deactivate toggle works on edit page', function () {
    $persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
        'status' => PersonaStatus::Active,
    ]);

    // Deactivate
    livewire(EditPersona::class, ['record' => $persona->getRouteKey()])
        ->callAction('toggle_active')
        ->assertNotified('Persona deactivated');

    $persona->refresh();
    expect($persona->is_active)->toBeFalse();
    expect($persona->status)->toBe(PersonaStatus::Paused);

    // Activate
    livewire(EditPersona::class, ['record' => $persona->getRouteKey()])
        ->callAction('toggle_active')
        ->assertNotified('Persona activated');

    $persona->refresh();
    expect($persona->is_active)->toBeTrue();
    expect($persona->status)->toBe(PersonaStatus::Active);
});

test('list page shows persona table with correct columns', function () {
    $persona = Persona::factory()->withRuns(5, 3)->create([
        'user_id' => $this->user->id,
    ]);

    livewire(ListPersonas::class)
        ->assertCanSeeTableRecords([$persona])
        ->assertSuccessful();
});

test('persona resource appears in automation navigation group', function () {
    expect(\App\Filament\Resources\Personas\PersonaResource::getNavigationGroup())->toBe('Automation');
});
