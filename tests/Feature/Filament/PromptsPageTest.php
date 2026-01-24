<?php

use App\Filament\Pages\Prompts;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->promptRelativePath = 'testing/editable.md';
    $this->promptPath = resource_path('prompts/'.$this->promptRelativePath);

    if (! File::isDirectory(dirname($this->promptPath))) {
        File::makeDirectory(dirname($this->promptPath), 0755, true);
    }

    File::put($this->promptPath, "Hello from test prompt\n");
});

afterEach(function () {
    if (isset($this->promptPath) && File::exists($this->promptPath)) {
        File::delete($this->promptPath);
    }

    $testDir = resource_path('prompts/testing');
    if (File::isDirectory($testDir)) {
        File::deleteDirectory($testDir);
    }
});

test('edit action pre-fills prompt content', function () {
    $component = Livewire::test(Prompts::class)
        ->assertSuccessful();

    $records = $component->instance()->getTableRecords();
    $recordKey = $records->search(fn ($record) => $record['id'] === $this->promptRelativePath);

    expect($recordKey)->not->toBeFalse();

    $component
        ->mountTableAction('edit', $recordKey)
        ->assertTableActionDataSet([
            'path' => $this->promptRelativePath,
            'content' => "Hello from test prompt\n",
        ]);
});
