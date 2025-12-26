# Repository Env Configs Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow users to manage multiple .env configurations per repository, with one set as default, auto-copied when tasks are created.

**Architecture:** New `RepositoryEnvConfig` model with one-to-many relationship to Repository. Filament view page for managing configs. CloneRepositoryJob auto-copies default .env. TaskChat gets a "Copy .env" button with dropdown.

**Tech Stack:** Laravel 12, Filament 4, Livewire 3, PHP 8.4

---

### Task 1: Create Migration

**Files:**
- Create: `database/migrations/2025_12_26_000001_create_repository_env_configs_table.php`

**Step 1: Generate migration**

Run:
```bash
php artisan make:migration create_repository_env_configs_table
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_env_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('content');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['repository_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_env_configs');
    }
};
```

**Step 3: Run migration**

Run:
```bash
php artisan migrate
```
Expected: Migration runs successfully, table created.

**Step 4: Commit**

```bash
git add database/migrations/*_create_repository_env_configs_table.php
git commit -m "feat: add repository_env_configs migration"
```

---

### Task 2: Create RepositoryEnvConfig Model

**Files:**
- Create: `app/Models/RepositoryEnvConfig.php`

**Step 1: Generate model**

Run:
```bash
php artisan make:model RepositoryEnvConfig
```

**Step 2: Write model content**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryEnvConfig extends Model
{
    protected $fillable = [
        'repository_id',
        'name',
        'content',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Set this config as the default, unsetting any other defaults for the same repository.
     */
    public function setAsDefault(): void
    {
        // Unset other defaults for this repository
        static::where('repository_id', $this->repository_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
```

**Step 3: Run pint**

Run:
```bash
vendor/bin/pint app/Models/RepositoryEnvConfig.php
```

**Step 4: Commit**

```bash
git add app/Models/RepositoryEnvConfig.php
git commit -m "feat: add RepositoryEnvConfig model"
```

---

### Task 3: Add Relationship to Repository Model

**Files:**
- Modify: `app/Models/Repository.php`

**Step 1: Add envConfigs relationship and helper methods**

Add after the `tasks()` method (around line 47):

```php
public function envConfigs(): HasMany
{
    return $this->hasMany(RepositoryEnvConfig::class);
}

public function defaultEnvConfig(): ?RepositoryEnvConfig
{
    return $this->envConfigs()->where('is_default', true)->first();
}
```

**Step 2: Add import at top of file**

The `HasMany` import is already present, no changes needed.

**Step 3: Run pint**

Run:
```bash
vendor/bin/pint app/Models/Repository.php
```

**Step 4: Commit**

```bash
git add app/Models/Repository.php
git commit -m "feat: add envConfigs relationship to Repository"
```

---

### Task 4: Create ViewRepository Filament Page

**Files:**
- Create: `app/Filament/Resources/RepositoryResource/Pages/ViewRepository.php`

**Step 1: Create the page file**

```php
<?php

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Filament\Resources\RepositoryResource;
use App\Models\Repository;
use App\Models\RepositoryEnvConfig;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewRepository extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = RepositoryResource::class;

    protected static string $view = 'filament.resources.repository-resource.pages.view-repository';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->full_name;
    }

    public function getBreadcrumb(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('github')
                ->label('View on GitHub')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url("https://github.com/{$this->record->full_name}")
                ->openUrlInNewTab(),

            Actions\Action::make('addEnvConfig')
                ->label('Add Env Config')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->placeholder('e.g., Local, Production, Staging')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('content')
                        ->label('.env Content')
                        ->placeholder("APP_NAME=MyApp\nAPP_ENV=local\n...")
                        ->required()
                        ->rows(15)
                        ->extraAttributes(['class' => 'font-mono text-sm']),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Set as default')
                        ->helperText('This config will be auto-copied when creating new tasks'),
                ])
                ->action(function (array $data): void {
                    $config = $this->record->envConfigs()->create($data);

                    if ($data['is_default']) {
                        $config->setAsDefault();
                    }

                    Notification::make()
                        ->title('Env config created')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => RepositoryEnvConfig::query()->where('repository_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('setDefault')
                    ->label('Set Default')
                    ->icon('heroicon-o-star')
                    ->visible(fn (RepositoryEnvConfig $record): bool => ! $record->is_default)
                    ->action(function (RepositoryEnvConfig $record): void {
                        $record->setAsDefault();

                        Notification::make()
                            ->title("'{$record->name}' is now the default")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('content')
                            ->label('.env Content')
                            ->required()
                            ->rows(15)
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as default'),
                    ])
                    ->fillForm(fn (RepositoryEnvConfig $record): array => [
                        'name' => $record->name,
                        'content' => $record->content,
                        'is_default' => $record->is_default,
                    ])
                    ->action(function (RepositoryEnvConfig $record, array $data): void {
                        $record->update($data);

                        if ($data['is_default']) {
                            $record->setAsDefault();
                        }

                        Notification::make()
                            ->title('Env config updated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (RepositoryEnvConfig $record, Tables\Actions\DeleteAction $action): void {
                        $count = $record->repository->envConfigs()->count();
                        if ($count === 1) {
                            Notification::make()
                                ->title('Cannot delete')
                                ->body('This is the only env config for this repository.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->emptyStateHeading('No env configs')
            ->emptyStateDescription('Add your first .env configuration for this repository.')
            ->emptyStateActions([
                Tables\Actions\Action::make('addFirst')
                    ->label('Add Env Config')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->placeholder('e.g., Local')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('content')
                            ->label('.env Content')
                            ->required()
                            ->rows(15)
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as default')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $config = $this->record->envConfigs()->create($data);

                        if ($data['is_default']) {
                            $config->setAsDefault();
                        }

                        Notification::make()
                            ->title('Env config created')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
```

**Step 2: Run pint**

Run:
```bash
vendor/bin/pint app/Filament/Resources/RepositoryResource/Pages/ViewRepository.php
```

**Step 3: Commit**

```bash
git add app/Filament/Resources/RepositoryResource/Pages/ViewRepository.php
git commit -m "feat: add ViewRepository page with env configs table"
```

---

### Task 5: Create View Repository Blade Template

**Files:**
- Create: `resources/views/filament/resources/repository-resource/pages/view-repository.blade.php`

**Step 1: Create directory structure**

Run:
```bash
mkdir -p resources/views/filament/resources/repository-resource/pages
```

**Step 2: Create the blade template**

```blade
<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->record->full_name }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->record->description ?: 'No description' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if($this->record->private)
                        <x-filament::badge color="warning" icon="heroicon-o-lock-closed">
                            Private
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="success" icon="heroicon-o-lock-open">
                            Public
                        </x-filament::badge>
                    @endif
                    <x-filament::badge color="gray">
                        {{ $this->record->default_branch }}
                    </x-filament::badge>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4">
                Environment Configurations
            </h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
```

**Step 3: Commit**

```bash
git add resources/views/filament/resources/repository-resource/pages/view-repository.blade.php
git commit -m "feat: add view-repository blade template"
```

---

### Task 6: Update RepositoryResource with View Page and Action

**Files:**
- Modify: `app/Filament/Resources/RepositoryResource.php`

**Step 1: Add view page to getPages() method**

Replace the `getPages()` method (around line 131):

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListRepositories::route('/'),
        'view' => Pages\ViewRepository::route('/{record}'),
    ];
}
```

**Step 2: Add "Manage Env" action to the table**

Add to the `recordActions` array (around line 61), before the github action:

```php
Actions\Action::make('manageEnv')
    ->label('Manage .env')
    ->icon('heroicon-o-cog-6-tooth')
    ->url(fn (Repository $record) => static::getUrl('view', ['record' => $record])),
```

**Step 3: Run pint**

Run:
```bash
vendor/bin/pint app/Filament/Resources/RepositoryResource.php
```

**Step 4: Commit**

```bash
git add app/Filament/Resources/RepositoryResource.php
git commit -m "feat: add manage env action and view page route"
```

---

### Task 7: Update CloneRepositoryJob to Auto-Copy Default .env

**Files:**
- Modify: `app/Jobs/CloneRepositoryJob.php`

**Step 1: Add auto-copy logic after successful clone**

Add after the "Repository cloned successfully" log (around line 56), before the closing brace:

```php
// Auto-copy default .env if one exists
$defaultEnvConfig = $repository->defaultEnvConfig();
if ($defaultEnvConfig) {
    $envPath = $workspacePath.'/.env';
    file_put_contents($envPath, $defaultEnvConfig->content);

    Log::info('Default .env config copied to workspace', [
        'task_id' => $this->task->id,
        'env_config' => $defaultEnvConfig->name,
        'env_path' => $envPath,
    ]);
}
```

**Step 2: Run pint**

Run:
```bash
vendor/bin/pint app/Jobs/CloneRepositoryJob.php
```

**Step 3: Commit**

```bash
git add app/Jobs/CloneRepositoryJob.php
git commit -m "feat: auto-copy default .env after cloning repository"
```

---

### Task 8: Add Copy .env Button to TaskChat Livewire Component

**Files:**
- Modify: `app/Livewire/TaskChat.php`

**Step 1: Add computed property for env configs**

Add after the `currentProvider()` computed property (around line 105):

```php
#[Computed]
public function envConfigs(): EloquentCollection
{
    if (! $this->task->repository) {
        return new EloquentCollection();
    }

    return $this->task->repository->envConfigs()->orderByDesc('is_default')->get();
}

#[Computed]
public function hasEnvConfigs(): bool
{
    return $this->envConfigs->isNotEmpty();
}

#[Computed]
public function defaultEnvConfig(): ?RepositoryEnvConfig
{
    return $this->envConfigs->firstWhere('is_default', true);
}
```

**Step 2: Add import for RepositoryEnvConfig at top of file**

Add after the existing use statements:

```php
use App\Models\RepositoryEnvConfig;
```

**Step 3: Add copyEnvConfig method**

Add after the `handleHelpCommand()` method (around line 248):

```php
public function copyEnvConfig(?int $configId = null): void
{
    if (! $this->task->workspace_path || ! is_dir($this->task->workspace_path)) {
        $this->dispatch('notify', [
            'message' => 'Workspace does not exist.',
            'type' => 'error',
        ]);

        return;
    }

    $config = $configId
        ? $this->task->repository->envConfigs()->find($configId)
        : $this->defaultEnvConfig;

    if (! $config) {
        $this->dispatch('notify', [
            'message' => 'No .env config found.',
            'type' => 'error',
        ]);

        return;
    }

    $envPath = $this->task->workspace_path.'/.env';
    file_put_contents($envPath, $config->content);

    $this->dispatch('notify', [
        'message' => "Copied '{$config->name}' .env to workspace.",
    ]);
}
```

**Step 4: Run pint**

Run:
```bash
vendor/bin/pint app/Livewire/TaskChat.php
```

**Step 5: Commit**

```bash
git add app/Livewire/TaskChat.php
git commit -m "feat: add copyEnvConfig method to TaskChat"
```

---

### Task 9: Update TaskChat Blade Template with Copy .env Button

**Files:**
- Modify: `resources/views/livewire/task-chat.blade.php`

**Step 1: Find the header buttons section**

Look for the section with "Deploy to Site" and "Delete Workspace" buttons.

**Step 2: Add the Copy .env button group before Deploy button**

Add the following before the Deploy to Site button:

```blade
@if($this->hasEnvConfigs)
    <div x-data="{ open: false }" class="relative">
        <div class="inline-flex rounded-lg shadow-sm">
            <button
                type="button"
                wire:click="copyEnvConfig"
                class="inline-flex items-center gap-1 rounded-l-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
            >
                <x-heroicon-o-document-duplicate class="h-4 w-4" />
                Copy .env
            </button>
            <button
                type="button"
                @click="open = !open"
                class="inline-flex items-center rounded-r-lg border-l border-gray-300 bg-gray-100 px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
            >
                <x-heroicon-o-chevron-down class="h-4 w-4" />
            </button>
        </div>

        <div
            x-show="open"
            @click.away="open = false"
            x-transition
            class="absolute right-0 z-10 mt-1 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-800 dark:ring-gray-700"
        >
            <div class="py-1">
                @foreach($this->envConfigs as $config)
                    <button
                        type="button"
                        wire:click="copyEnvConfig({{ $config->id }})"
                        @click="open = false"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        @if($config->is_default)
                            <x-heroicon-o-star class="h-4 w-4 text-yellow-500" />
                        @else
                            <span class="h-4 w-4"></span>
                        @endif
                        {{ $config->name }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>
@endif
```

**Step 3: Commit**

```bash
git add resources/views/livewire/task-chat.blade.php
git commit -m "feat: add Copy .env button with dropdown to TaskChat"
```

---

### Task 10: Run Pint and Final Verification

**Step 1: Run pint on all modified files**

Run:
```bash
vendor/bin/pint --dirty
```

**Step 2: Clear caches**

Run:
```bash
php artisan optimize:clear
```

**Step 3: Verify everything works**

Run:
```bash
php artisan serve
```

Navigate to:
1. `/admin/repositories` - Click "Manage .env" on a repository
2. Add an env config, set as default
3. Create a new task for that repository
4. Verify .env was auto-copied
5. Click "Copy .env" button to test manual copy

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete repository env configs feature"
```

---

## Summary

This plan implements:
- `RepositoryEnvConfig` model with multiple configs per repository
- Default config flag with automatic unsetting of other defaults
- Filament view page for managing env configs
- Auto-copy of default .env in `CloneRepositoryJob`
- "Copy .env" button with dropdown in TaskChat
