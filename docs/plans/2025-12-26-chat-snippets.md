# Chat Snippets Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add reusable text snippets that users can quickly insert into chat conversations via a tabbed sidebar.

**Architecture:** Create a Snippet model owned by users, a Filament resource for CRUD, a SnippetBrowser Livewire component for the sidebar, and wire up snippet insertion via Livewire events.

**Tech Stack:** Laravel 12, Filament v4, Livewire v3, Pest for testing

---

## Task 1: Create Snippet Model and Migration

**Files:**
- Create: `app/Models/Snippet.php`
- Create: `database/migrations/2025_12_26_120000_create_snippets_table.php`
- Create: `database/factories/SnippetFactory.php`
- Modify: `app/Models/User.php:72` (add relationship)

**Step 1: Create the migration**

Run:
```bash
php artisan make:migration create_snippets_table --no-interaction
```

Then replace content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snippets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('content');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snippets');
    }
};
```

**Step 2: Run the migration**

Run: `php artisan migrate`
Expected: Migration runs successfully

**Step 3: Create the Snippet model**

Create `app/Models/Snippet.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snippet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'content',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 4: Create the factory**

Create `database/factories/SnippetFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Snippet>
 */
class SnippetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'content' => fake()->paragraph(),
            'sort_order' => 0,
        ];
    }
}
```

**Step 5: Add relationship to User model**

Modify `app/Models/User.php`, add after line 75 (after repositories method):

```php
public function snippets(): HasMany
{
    return $this->hasMany(Snippet::class)->orderBy('sort_order');
}
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat: add Snippet model with migration and factory

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 2: Create Snippet Model Tests

**Files:**
- Create: `tests/Feature/Models/SnippetTest.php`

**Step 1: Create the test file**

Create `tests/Feature/Models/SnippetTest.php`:

```php
<?php

use App\Models\Snippet;
use App\Models\User;

test('snippet belongs to a user', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()->create(['user_id' => $user->id]);

    expect($snippet->user->id)->toBe($user->id);
});

test('user has many snippets', function () {
    $user = User::factory()->create();
    Snippet::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->snippets)->toHaveCount(3);
});

test('snippets are ordered by sort_order', function () {
    $user = User::factory()->create();
    Snippet::factory()->create(['user_id' => $user->id, 'sort_order' => 2, 'name' => 'Second']);
    Snippet::factory()->create(['user_id' => $user->id, 'sort_order' => 0, 'name' => 'First']);
    Snippet::factory()->create(['user_id' => $user->id, 'sort_order' => 1, 'name' => 'Middle']);

    $snippets = $user->snippets;

    expect($snippets[0]->name)->toBe('First');
    expect($snippets[1]->name)->toBe('Middle');
    expect($snippets[2]->name)->toBe('Second');
});

test('snippet can be created with required fields', function () {
    $user = User::factory()->create();

    $snippet = Snippet::create([
        'user_id' => $user->id,
        'name' => 'Test Snippet',
        'content' => 'This is the snippet content',
    ]);

    expect($snippet->exists)->toBeTrue();
    expect($snippet->name)->toBe('Test Snippet');
    expect($snippet->content)->toBe('This is the snippet content');
    expect($snippet->sort_order)->toBe(0);
});

test('deleting user deletes their snippets', function () {
    $user = User::factory()->create();
    Snippet::factory()->count(2)->create(['user_id' => $user->id]);

    expect(Snippet::where('user_id', $user->id)->count())->toBe(2);

    $user->delete();

    expect(Snippet::where('user_id', $user->id)->count())->toBe(0);
});
```

**Step 2: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Models/SnippetTest.php`
Expected: All 5 tests pass

**Step 3: Commit**

```bash
git add -A && git commit -m "test: add Snippet model tests

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 3: Create Snippet Filament Resource

**Files:**
- Create: `app/Filament/Resources/SnippetResource.php`
- Create: `app/Filament/Resources/SnippetResource/Pages/ListSnippets.php`
- Create: `app/Filament/Resources/SnippetResource/Pages/CreateSnippet.php`
- Create: `app/Filament/Resources/SnippetResource/Pages/EditSnippet.php`

**Step 1: Create the resource using artisan**

Run:
```bash
php artisan make:filament-resource Snippet --generate --no-interaction
```

**Step 2: Replace SnippetResource with custom implementation**

Replace `app/Filament/Resources/SnippetResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SnippetResource\Pages;
use App\Models\Snippet;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SnippetResource extends Resource
{
    protected static ?string $model = Snippet::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Playwright Login'),

                Forms\Components\Textarea::make('content')
                    ->required()
                    ->rows(10)
                    ->placeholder('The text that will be inserted into the chat...'),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first'),

                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => Auth::id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('content')
                    ->limit(50)
                    ->tooltip(fn (Snippet $record) => Str::limit($record->content, 200)),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No snippets yet')
            ->emptyStateDescription('Create reusable text snippets to quickly insert into chats.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSnippets::route('/'),
            'create' => Pages\CreateSnippet::route('/create'),
            'edit' => Pages\EditSnippet::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Verify resource pages exist**

Check that these files were created by artisan:
- `app/Filament/Resources/SnippetResource/Pages/ListSnippets.php`
- `app/Filament/Resources/SnippetResource/Pages/CreateSnippet.php`
- `app/Filament/Resources/SnippetResource/Pages/EditSnippet.php`

If not, create them manually following the pattern from other resources.

**Step 4: Run the app and verify resource works**

Run: `php artisan serve` (or refresh browser)
Navigate to: `/admin/snippets`
Expected: Empty state with "No snippets yet" message

**Step 5: Commit**

```bash
git add -A && git commit -m "feat: add Snippet Filament resource

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 4: Create SnippetBrowser Livewire Component

**Files:**
- Create: `app/Livewire/SnippetBrowser.php`
- Create: `resources/views/livewire/snippet-browser.blade.php`

**Step 1: Create the Livewire component**

Create `app/Livewire/SnippetBrowser.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Snippet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SnippetBrowser extends Component
{
    /**
     * @return Collection<int, Snippet>
     */
    #[Computed]
    public function snippets(): Collection
    {
        return Snippet::where('user_id', Auth::id())
            ->orderBy('sort_order')
            ->get();
    }

    public function insertSnippet(int $snippetId): void
    {
        $snippet = Snippet::where('user_id', Auth::id())
            ->find($snippetId);

        if ($snippet) {
            $this->dispatch('insert-snippet', content: $snippet->content);
        }
    }

    public function render()
    {
        return view('livewire.snippet-browser');
    }
}
```

**Step 2: Create the blade view**

Create `resources/views/livewire/snippet-browser.blade.php`:

```blade
<div class="file-browser">
    <div class="file-browser-header">
        <h3 class="file-browser-title">Snippets</h3>
        <p class="file-browser-path">Click to insert</p>
    </div>

    <div class="file-browser-list">
        @forelse($this->snippets as $snippet)
            <button
                wire:key="snippet-{{ $snippet->id }}"
                wire:click="insertSnippet({{ $snippet->id }})"
                class="file-browser-item"
                title="{{ $snippet->content }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span class="file-browser-item-name">{{ $snippet->name }}</span>
            </button>
        @empty
            <div style="padding: 1rem; text-align: center; color: rgb(107 114 128); font-size: 0.875rem;">
                <p>No snippets yet</p>
                <a href="{{ route('filament.admin.resources.snippets.index') }}" style="color: rgb(59 130 246); text-decoration: underline;">
                    Create one
                </a>
            </div>
        @endforelse
    </div>
</div>
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat: add SnippetBrowser Livewire component

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 5: Create SnippetBrowser Tests

**Files:**
- Create: `tests/Feature/Livewire/SnippetBrowserTest.php`

**Step 1: Create the test file**

Create `tests/Feature/Livewire/SnippetBrowserTest.php`:

```php
<?php

use App\Livewire\SnippetBrowser;
use App\Models\Snippet;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can render snippet browser component', function () {
    Livewire::test(SnippetBrowser::class)
        ->assertSuccessful()
        ->assertSee('Snippets');
});

test('shows empty state when no snippets', function () {
    Livewire::test(SnippetBrowser::class)
        ->assertSee('No snippets yet')
        ->assertSee('Create one');
});

test('displays user snippets', function () {
    Snippet::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'My Test Snippet',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->assertSee('My Test Snippet');
});

test('does not show other users snippets', function () {
    $otherUser = User::factory()->create();
    Snippet::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Other User Snippet',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->assertDontSee('Other User Snippet')
        ->assertSee('No snippets yet');
});

test('dispatches insert-snippet event when clicking snippet', function () {
    $snippet = Snippet::factory()->create([
        'user_id' => $this->user->id,
        'content' => 'This is the snippet content to insert',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->call('insertSnippet', $snippet->id)
        ->assertDispatched('insert-snippet', content: 'This is the snippet content to insert');
});

test('does not dispatch event for other users snippets', function () {
    $otherUser = User::factory()->create();
    $snippet = Snippet::factory()->create([
        'user_id' => $otherUser->id,
        'content' => 'Should not insert this',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->call('insertSnippet', $snippet->id)
        ->assertNotDispatched('insert-snippet');
});

test('snippets are ordered by sort_order', function () {
    Snippet::factory()->create(['user_id' => $this->user->id, 'name' => 'Third', 'sort_order' => 2]);
    Snippet::factory()->create(['user_id' => $this->user->id, 'name' => 'First', 'sort_order' => 0]);
    Snippet::factory()->create(['user_id' => $this->user->id, 'name' => 'Second', 'sort_order' => 1]);

    Livewire::test(SnippetBrowser::class)
        ->assertSeeInOrder(['First', 'Second', 'Third']);
});
```

**Step 2: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Livewire/SnippetBrowserTest.php`
Expected: All 7 tests pass

**Step 3: Commit**

```bash
git add -A && git commit -m "test: add SnippetBrowser component tests

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 6: Add Tabbed Sidebar to Chat Views

**Files:**
- Modify: `resources/views/filament/resources/tasks/task-resource/pages/task-chat.blade.php`
- Modify: `resources/views/filament/resources/general-chat-resource/pages/general-chat-page.blade.php`
- Modify: `resources/css/filament/chat.css`

**Step 1: Add tab styles to chat.css**

Add to the end of `resources/css/filament/chat.css`:

```css
/* Sidebar tabs */
.sidebar-tabs {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    overflow: hidden;
}

.sidebar-tab-buttons {
    display: flex;
    gap: 0;
    flex-shrink: 0;
    border-bottom: 1px solid rgb(229 231 235);
}

.dark .sidebar-tab-buttons {
    border-bottom-color: rgb(55 65 81);
}

.sidebar-tab-btn {
    flex: 1;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    background: transparent;
    border: none;
    cursor: pointer;
    color: rgb(107 114 128);
    transition: all 0.15s;
}

.sidebar-tab-btn:hover {
    color: rgb(55 65 81);
    background-color: rgb(243 244 246);
}

.dark .sidebar-tab-btn:hover {
    color: rgb(209 213 219);
    background-color: rgb(55 65 81);
}

.sidebar-tab-btn-active {
    color: rgb(37 99 235);
    border-bottom: 2px solid rgb(37 99 235);
}

.dark .sidebar-tab-btn-active {
    color: rgb(96 165 250);
    border-bottom-color: rgb(96 165 250);
}

.sidebar-tab-content {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
```

**Step 2: Update task-chat.blade.php with tabbed sidebar**

Replace `resources/views/filament/resources/tasks/task-resource/pages/task-chat.blade.php`:

```blade
<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; height: calc(100vh - 16rem); overflow: hidden;">
        {{-- Chat Area (2/3 width) --}}
        <div style="height: 100%; min-height: 0; overflow: hidden;">
            <div style="height: 100%;">
                @livewire('task-chat', ['task' => $this->getRecord()])
            </div>
        </div>

        {{-- Sidebar with Tabs (1/3 width) --}}
        <div style="height: 100%; min-height: 0; overflow: hidden;">
            <div class="sidebar-tabs" x-data="{ activeTab: 'files' }">
                <div class="sidebar-tab-buttons">
                    <button
                        @click="activeTab = 'files'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'files' }"
                        class="sidebar-tab-btn"
                    >
                        Files
                    </button>
                    <button
                        @click="activeTab = 'snippets'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'snippets' }"
                        class="sidebar-tab-btn"
                    >
                        Snippets
                    </button>
                </div>
                <div class="sidebar-tab-content">
                    <div x-show="activeTab === 'files'" style="height: 100%;">
                        @livewire('file-browser', ['basePath' => $this->getRecord()->workspace_path ?? $this->getRecord()->site?->path])
                    </div>
                    <div x-show="activeTab === 'snippets'" x-cloak style="height: 100%;">
                        @livewire('snippet-browser')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```

**Step 3: Update general-chat-page.blade.php with tabbed sidebar**

Replace `resources/views/filament/resources/general-chat-resource/pages/general-chat-page.blade.php`:

```blade
<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; height: calc(100vh - 16rem); overflow: hidden;">
        {{-- Chat Area (2/3 width) --}}
        <div style="height: 100%; min-height: 0; overflow: hidden;">
            <div style="height: 100%;">
                @livewire('general-chat-box', ['chat' => $this->getRecord()])
            </div>
        </div>

        {{-- Sidebar with Tabs (1/3 width) --}}
        <div style="height: 100%; min-height: 0; overflow: hidden;">
            <div class="sidebar-tabs" x-data="{ activeTab: 'files' }">
                <div class="sidebar-tab-buttons">
                    <button
                        @click="activeTab = 'files'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'files' }"
                        class="sidebar-tab-btn"
                    >
                        Files
                    </button>
                    <button
                        @click="activeTab = 'snippets'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'snippets' }"
                        class="sidebar-tab-btn"
                    >
                        Snippets
                    </button>
                </div>
                <div class="sidebar-tab-content">
                    <div x-show="activeTab === 'files'" style="height: 100%;">
                        @livewire('file-browser', ['basePath' => $this->getRecord()->working_directory])
                    </div>
                    <div x-show="activeTab === 'snippets'" x-cloak style="height: 100%;">
                        @livewire('snippet-browser')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat: add tabbed sidebar with Files and Snippets tabs

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 7: Wire Up Snippet Insertion to Chat Components

**Files:**
- Modify: `app/Livewire/TaskChat.php`
- Modify: `app/Livewire/GeneralChatBox.php`

**Step 1: Add insertSnippet listener to TaskChat**

Add to `app/Livewire/TaskChat.php`, after the `mount` method:

```php
#[On('insert-snippet')]
public function insertSnippet(string $content): void
{
    if (! empty($this->prompt)) {
        $this->prompt .= "\n\n";
    }
    $this->prompt .= $content;
}
```

Also add the import at the top:
```php
use Livewire\Attributes\On;
```

**Step 2: Add insertSnippet listener to GeneralChatBox**

Add to `app/Livewire/GeneralChatBox.php`, after the `mount` method:

```php
#[On('insert-snippet')]
public function insertSnippet(string $content): void
{
    if (! empty($this->prompt)) {
        $this->prompt .= "\n\n";
    }
    $this->prompt .= $content;
}
```

Also add the import at the top if not already present:
```php
use Livewire\Attributes\On;
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat: wire up snippet insertion to chat components

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 8: Add Snippet Insertion Tests

**Files:**
- Modify: `tests/Feature/Livewire/TaskChatTest.php`
- Modify: `tests/Feature/Livewire/GeneralChatBoxTest.php`

**Step 1: Add snippet insertion test to TaskChatTest**

Add to `tests/Feature/Livewire/TaskChatTest.php`:

```php
test('can insert snippet into prompt', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->dispatch('insert-snippet', content: 'Inserted snippet text')
        ->assertSet('prompt', 'Inserted snippet text');
});

test('appends snippet to existing prompt with newlines', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'Existing text')
        ->dispatch('insert-snippet', content: 'Inserted snippet')
        ->assertSet('prompt', "Existing text\n\nInserted snippet");
});
```

**Step 2: Add snippet insertion test to GeneralChatBoxTest**

Add to `tests/Feature/Livewire/GeneralChatBoxTest.php`:

```php
test('can insert snippet into prompt', function () {
    $chat = GeneralChat::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(GeneralChatBox::class, ['chat' => $chat])
        ->dispatch('insert-snippet', content: 'Inserted snippet text')
        ->assertSet('prompt', 'Inserted snippet text');
});

test('appends snippet to existing prompt with newlines', function () {
    $chat = GeneralChat::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(GeneralChatBox::class, ['chat' => $chat])
        ->set('prompt', 'Existing text')
        ->dispatch('insert-snippet', content: 'Inserted snippet')
        ->assertSet('prompt', "Existing text\n\nInserted snippet");
});
```

**Step 3: Run all tests**

Run: `php artisan test`
Expected: All tests pass

**Step 4: Commit**

```bash
git add -A && git commit -m "test: add snippet insertion tests for chat components

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Task 9: Final Verification and Cleanup

**Step 1: Run full test suite**

Run: `php artisan test`
Expected: All tests pass

**Step 2: Clear caches**

Run:
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

**Step 3: Manual verification**

1. Navigate to `/admin/snippets`
2. Create a test snippet with name "Test" and content "Hello from snippet!"
3. Navigate to any task or general chat
4. Click "Snippets" tab in sidebar
5. Click the snippet - verify it inserts into textarea
6. Type some text, click snippet again - verify it appends with newlines

**Step 4: Final commit**

```bash
git add -A && git commit -m "chore: final cleanup for chat snippets feature

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
```

---

## Summary

This implementation adds:
- **Snippet model** with user ownership and sort ordering
- **Filament resource** for CRUD at `/admin/snippets`
- **SnippetBrowser component** for sidebar display
- **Tabbed sidebar** in both TaskChat and GeneralChat views
- **Event-driven insertion** via Livewire dispatch
- **Comprehensive tests** for all new functionality
