<?php

use App\Filament\Resources\Personas\Pages\EditPersona;
use App\Models\Persona;
use App\Models\Proposal;
use App\Models\User;
use App\Services\PersonaStorageService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

afterEach(function () {
    // Clean up persona storage directories created during tests
    if (isset($this->persona)) {
        $path = $this->persona->getStoragePath();
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }
});

test('edit page renders with proposals relation manager', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $proposals = Proposal::factory()->count(3)->create([
        'persona_id' => $this->persona->id,
    ]);

    livewire(EditPersona::class, ['record' => $this->persona->getRouteKey()])
        ->assertSuccessful();
});

test('edit page shows stats widget', function () {
    $this->persona = Persona::factory()->withRuns(10, 5)->create(['user_id' => $this->user->id]);

    livewire(EditPersona::class, ['record' => $this->persona->getRouteKey()])
        ->assertSuccessful();
});

test('storage viewer reads and displays files correctly', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $storageService = app(PersonaStorageService::class);

    // Storage should be initialized by the observer
    $tree = $storageService->getDirectoryTree($this->persona);

    expect($tree)->toBeArray();
    expect($tree)->not->toBeEmpty();

    // Should have context.md file
    $fileNames = collect($tree)->where('type', 'file')->pluck('name')->all();
    $dirNames = collect($tree)->where('type', 'directory')->pluck('name')->all();

    expect($fileNames)->toContain('context.md');
    expect($fileNames)->toContain('prompt.md');
    expect($dirNames)->toContain('history');
    expect($dirNames)->toContain('completed-plans');

    // context.md should be readable
    $contextContent = $storageService->readContext($this->persona);
    expect($contextContent)->not->toBeNull();
    expect($contextContent)->toContain($this->persona->name);
});

test('storage viewer can read individual files', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $storageService = app(PersonaStorageService::class);

    // Read prompt.md
    $content = $storageService->readFile($this->persona, 'prompt.md');
    expect($content)->toBe($this->persona->master_prompt);

    // Read context.md
    $content = $storageService->readFile($this->persona, 'context.md');
    expect($content)->not->toBeNull();
});

test('edit context action writes to context.md', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $newContent = '# Updated Context';

    livewire(EditPersona::class, ['record' => $this->persona->getRouteKey()])
        ->callAction('edit_context', data: [
            'context_content' => $newContent,
        ])
        ->assertNotified('Context updated');

    $storageService = app(PersonaStorageService::class);
    expect($storageService->readContext($this->persona))->toBe($newContent);
});

test('history files are returned newest first', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $historyPath = "{$this->persona->getStoragePath()}/history";
    File::ensureDirectoryExists($historyPath);

    // Create files with different timestamps
    File::put("{$historyPath}/2026-03-01-cycle-1.md", '# Cycle 1');
    touch("{$historyPath}/2026-03-01-cycle-1.md", strtotime('2026-03-01'));

    File::put("{$historyPath}/2026-03-05-cycle-2.md", '# Cycle 2');
    touch("{$historyPath}/2026-03-05-cycle-2.md", strtotime('2026-03-05'));

    File::put("{$historyPath}/2026-03-08-cycle-3.md", '# Cycle 3');
    touch("{$historyPath}/2026-03-08-cycle-3.md", strtotime('2026-03-08'));

    $storageService = app(PersonaStorageService::class);
    $historyFiles = $storageService->getHistoryFiles($this->persona);

    expect($historyFiles)->toHaveCount(3);
    expect($historyFiles[0]['name'])->toBe('2026-03-08-cycle-3.md');
    expect($historyFiles[1]['name'])->toBe('2026-03-05-cycle-2.md');
    expect($historyFiles[2]['name'])->toBe('2026-03-01-cycle-1.md');
    expect($historyFiles[0]['content'])->toBe('# Cycle 3');
});

test('view storage action shows modal', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    livewire(EditPersona::class, ['record' => $this->persona->getRouteKey()])
        ->assertActionExists('view_storage');
});
