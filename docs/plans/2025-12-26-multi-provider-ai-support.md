# Multi-Provider AI Support (Claude + z.ai GLM) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add support for multiple AI providers (Claude and z.ai GLM) with per-chat/task provider selection and quota tracking dashboard.

**Architecture:** Create an `AiProvider` model to store provider configs. Modify `GeneralChat` and `Task` models to reference a provider. Update job classes to set environment variables based on selected provider. Add a Filament widget for quota visualization and a settings page for provider configuration.

**Tech Stack:** Laravel 12, Filament 4, Livewire 3, Tailwind CSS 4

---

## Task 1: Create AiProvider Model and Migration

**Files:**
- Create: `database/migrations/2025_12_26_000001_create_ai_providers_table.php`
- Create: `app/Models/AiProvider.php`
- Create: `database/factories/AiProviderFactory.php`
- Create: `database/seeders/AiProviderSeeder.php`
- Create: `tests/Feature/Models/AiProviderTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Models/AiProviderTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_provider(): void
    {
        $provider = AiProvider::factory()->create([
            'name' => 'claude',
            'display_name' => 'Claude',
        ]);

        expect($provider->name)->toBe('claude')
            ->and($provider->display_name)->toBe('Claude')
            ->and($provider->is_active)->toBeTrue();
    }

    public function test_it_encrypts_api_key(): void
    {
        $provider = AiProvider::factory()->create([
            'api_key' => 'secret-key-123',
        ]);

        $provider->refresh();

        expect($provider->api_key)->toBe('secret-key-123');
        $this->assertDatabaseMissing('ai_providers', [
            'api_key' => 'secret-key-123',
        ]);
    }

    public function test_it_returns_env_array_for_glm(): void
    {
        $provider = AiProvider::factory()->glm()->create();

        $env = $provider->getEnvironmentVariables();

        expect($env)->toHaveKey('ANTHROPIC_BASE_URL')
            ->and($env)->toHaveKey('ANTHROPIC_AUTH_TOKEN')
            ->and($env)->toHaveKey('ANTHROPIC_MODEL');
    }

    public function test_it_returns_empty_env_array_for_claude(): void
    {
        $provider = AiProvider::factory()->claude()->create();

        $env = $provider->getEnvironmentVariables();

        expect($env)->toBeEmpty();
    }

    public function test_default_scope_returns_first_active(): void
    {
        AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create();

        $default = AiProvider::getDefault();

        expect($default->name)->toBe('claude');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/AiProviderTest.php`
Expected: FAIL with "Table 'ai_providers' doesn't exist"

**Step 3: Create migration**

Run: `php artisan make:migration create_ai_providers_table --no-interaction`

Then update the migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'claude', 'glm'
            $table->string('display_name');
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable(); // encrypted
            $table->string('model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('quota_limit')->nullable(); // tokens per period
            $table->string('quota_period')->default('monthly'); // 'monthly', '5-hour'
            $table->unsignedBigInteger('quota_used')->default(0);
            $table->timestamp('quota_resets_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
```

**Step 4: Create AiProvider model**

Run: `php artisan make:model AiProvider --no-interaction`

Then update `app/Models/AiProvider.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'base_url',
        'api_key',
        'model',
        'is_active',
        'is_default',
        'quota_limit',
        'quota_period',
        'quota_used',
        'quota_resets_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'quota_limit' => 'integer',
            'quota_used' => 'integer',
            'quota_resets_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getEnvironmentVariables(): array
    {
        if ($this->name === 'claude') {
            return [];
        }

        return array_filter([
            'ANTHROPIC_BASE_URL' => $this->base_url,
            'ANTHROPIC_AUTH_TOKEN' => $this->api_key,
            'ANTHROPIC_MODEL' => $this->model,
        ]);
    }

    public function isGlm(): bool
    {
        return $this->name === 'glm';
    }

    public function isClaude(): bool
    {
        return $this->name === 'claude';
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('name', 'claude')->where('is_active', true)->first()
            ?? static::where('is_active', true)->first();
    }

    public function getQuotaPercentage(): float
    {
        if (! $this->quota_limit || $this->quota_limit === 0) {
            return 0;
        }

        return min(100, ($this->quota_used / $this->quota_limit) * 100);
    }

    public function incrementUsage(int $tokensIn, int $tokensOut): void
    {
        $this->increment('quota_used', $tokensIn + $tokensOut);
    }

    public function resetQuotaIfNeeded(): void
    {
        if (! $this->quota_resets_at || now()->greaterThan($this->quota_resets_at)) {
            $this->update([
                'quota_used' => 0,
                'quota_resets_at' => $this->calculateNextReset(),
            ]);
        }
    }

    protected function calculateNextReset(): \DateTime
    {
        return match ($this->quota_period) {
            '5-hour' => now()->addHours(5),
            default => now()->startOfMonth()->addMonth(),
        };
    }
}
```

**Step 5: Create factory**

Run: `php artisan make:factory AiProviderFactory --no-interaction`

Update `database/factories/AiProviderFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AiProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'claude',
            'display_name' => 'Claude',
            'base_url' => null,
            'api_key' => null,
            'model' => null,
            'is_active' => true,
            'is_default' => true,
            'quota_limit' => 10000000,
            'quota_period' => 'monthly',
            'quota_used' => 0,
            'quota_resets_at' => now()->startOfMonth()->addMonth(),
        ];
    }

    public function claude(): static
    {
        return $this->state([
            'name' => 'claude',
            'display_name' => 'Claude',
            'base_url' => null,
            'api_key' => null,
            'model' => null,
            'is_default' => true,
            'quota_period' => 'monthly',
        ]);
    }

    public function glm(): static
    {
        return $this->state([
            'name' => 'glm',
            'display_name' => 'GLM (z.ai)',
            'base_url' => 'https://api.z.ai/api/anthropic',
            'api_key' => 'test-api-key',
            'model' => 'GLM-4.6',
            'is_default' => false,
            'quota_period' => '5-hour',
            'quota_limit' => 50000000,
            'quota_resets_at' => now()->addHours(5),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

**Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Models/AiProviderTest.php`
Expected: PASS (all 5 tests)

**Step 7: Create seeder**

Run: `php artisan make:seeder AiProviderSeeder --no-interaction`

Update `database/seeders/AiProviderSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        AiProvider::firstOrCreate(
            ['name' => 'claude'],
            [
                'display_name' => 'Claude',
                'is_active' => true,
                'is_default' => true,
                'quota_limit' => 10000000,
                'quota_period' => 'monthly',
                'quota_resets_at' => now()->startOfMonth()->addMonth(),
            ]
        );

        AiProvider::firstOrCreate(
            ['name' => 'glm'],
            [
                'display_name' => 'GLM (z.ai)',
                'base_url' => 'https://api.z.ai/api/anthropic',
                'model' => 'GLM-4.6',
                'is_active' => false,
                'is_default' => false,
                'quota_limit' => 50000000,
                'quota_period' => '5-hour',
                'quota_resets_at' => now()->addHours(5),
            ]
        );
    }
}
```

**Step 8: Run migration and seeder**

Run: `php artisan migrate && php artisan db:seed --class=AiProviderSeeder`

**Step 9: Run Pint and commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add AiProvider model with quota tracking"
```

---

## Task 2: Add Provider Relationship to GeneralChat

**Files:**
- Create: `database/migrations/2025_12_26_000002_add_ai_provider_to_general_chats_table.php`
- Modify: `app/Models/GeneralChat.php:16-35`
- Modify: `database/factories/GeneralChatFactory.php:13-25`
- Modify: `tests/Feature/Models/GeneralChatTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Models/GeneralChatTest.php`:

```php
public function test_it_belongs_to_ai_provider(): void
{
    $provider = AiProvider::factory()->create();
    $chat = GeneralChat::factory()->create(['ai_provider_id' => $provider->id]);

    expect($chat->aiProvider)->toBeInstanceOf(AiProvider::class)
        ->and($chat->aiProvider->id)->toBe($provider->id);
}

public function test_it_uses_default_provider_when_none_specified(): void
{
    AiProvider::factory()->claude()->create();
    $chat = GeneralChat::factory()->create();

    expect($chat->aiProvider)->not->toBeNull()
        ->and($chat->aiProvider->name)->toBe('claude');
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/GeneralChatTest.php --filter=provider`
Expected: FAIL

**Step 3: Create migration**

Run: `php artisan make:migration add_ai_provider_to_general_chats_table --no-interaction`

Update the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_chats', function (Blueprint $table) {
            $table->foreignId('ai_provider_id')->nullable()->after('user_id')->constrained('ai_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('general_chats', function (Blueprint $table) {
            $table->dropForeign(['ai_provider_id']);
            $table->dropColumn('ai_provider_id');
        });
    }
};
```

**Step 4: Update GeneralChat model**

In `app/Models/GeneralChat.php`, add to `$fillable`:

```php
protected $fillable = [
    'uuid',
    'user_id',
    'ai_provider_id', // Add this
    'session_id',
    'title',
    'working_directory',
    'status',
    'started_at',
    'completed_at',
];
```

Add relationship method after `user()`:

```php
public function aiProvider(): BelongsTo
{
    return $this->belongsTo(AiProvider::class);
}
```

Update `booted()` to assign default provider:

```php
protected static function booted(): void
{
    static::creating(function (GeneralChat $chat) {
        $chat->uuid ??= Str::uuid();
        $chat->session_id ??= Str::uuid();
        $chat->working_directory ??= '/home/ploi';
        $chat->ai_provider_id ??= AiProvider::getDefault()?->id;
    });
}
```

Add import at top:

```php
use App\Models\AiProvider;
```

**Step 5: Update factory**

In `database/factories/GeneralChatFactory.php`, update `definition()`:

```php
public function definition(): array
{
    return [
        'uuid' => Str::uuid(),
        'user_id' => User::factory(),
        'ai_provider_id' => null, // Will use default in model boot
        'session_id' => Str::uuid(),
        'title' => fake()->optional()->sentence(3),
        'working_directory' => '/home/ploi',
        'status' => GeneralChatStatus::Pending,
        'started_at' => null,
        'completed_at' => null,
    ];
}
```

Add state method:

```php
public function withProvider(AiProvider $provider): static
{
    return $this->state(['ai_provider_id' => $provider->id]);
}
```

Add import:

```php
use App\Models\AiProvider;
```

**Step 6: Run migration and tests**

Run: `php artisan migrate && php artisan test tests/Feature/Models/GeneralChatTest.php`
Expected: PASS

**Step 7: Run Pint and commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add ai_provider relationship to GeneralChat"
```

---

## Task 3: Update RunGeneralChatMessageJob to Use Provider

**Files:**
- Modify: `app/Jobs/RunGeneralChatMessageJob.php:36-52,118-134`
- Modify: `tests/Feature/Jobs/RunGeneralChatMessageJobTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Jobs/RunGeneralChatMessageJobTest.php`:

```php
public function test_it_builds_command_with_glm_env_vars(): void
{
    $provider = AiProvider::factory()->glm()->create();
    $chat = GeneralChat::factory()->create(['ai_provider_id' => $provider->id]);
    $message = GeneralChatMessage::factory()->for($chat, 'chat')->create();

    $job = new RunGeneralChatMessageJob($chat, $message, continue: false);

    $env = $job->getProviderEnvironment();

    expect($env)->toHaveKey('ANTHROPIC_BASE_URL')
        ->and($env['ANTHROPIC_BASE_URL'])->toBe('https://api.z.ai/api/anthropic')
        ->and($env)->toHaveKey('ANTHROPIC_MODEL')
        ->and($env['ANTHROPIC_MODEL'])->toBe('GLM-4.6');
}

public function test_it_returns_empty_env_for_claude(): void
{
    $provider = AiProvider::factory()->claude()->create();
    $chat = GeneralChat::factory()->create(['ai_provider_id' => $provider->id]);
    $message = GeneralChatMessage::factory()->for($chat, 'chat')->create();

    $job = new RunGeneralChatMessageJob($chat, $message, continue: false);

    $env = $job->getProviderEnvironment();

    expect($env)->toBeEmpty();
}
```

Add import at top:

```php
use App\Models\AiProvider;
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Jobs/RunGeneralChatMessageJobTest.php --filter=env`
Expected: FAIL with "method getProviderEnvironment not found"

**Step 3: Update job to use provider environment**

In `app/Jobs/RunGeneralChatMessageJob.php`, add new method after `buildCommand()`:

```php
/**
 * @return array<string, string>
 */
public function getProviderEnvironment(): array
{
    $provider = $this->chat->aiProvider;

    if (! $provider) {
        return [];
    }

    return $provider->getEnvironmentVariables();
}
```

Update the `proc_open` call in `handle()` to include env vars. Replace:

```php
$process = proc_open($command, $descriptors, $pipes, $workingDir);
```

With:

```php
$env = array_merge($_ENV, $_SERVER, $this->getProviderEnvironment());
$process = proc_open($command, $descriptors, $pipes, $workingDir, $env);
```

Also add after usage parsing (around line 89) to track provider usage:

```php
if (isset($parsed['usage'])) {
    $assistantMessage->update([
        'tokens_in' => $parsed['usage']['input_tokens'] ?? null,
        'tokens_out' => $parsed['usage']['output_tokens'] ?? null,
        'cost_usd' => $parsed['usage']['cost_usd'] ?? null,
    ]);

    // Track provider usage
    if ($this->chat->aiProvider) {
        $this->chat->aiProvider->incrementUsage(
            $parsed['usage']['input_tokens'] ?? 0,
            $parsed['usage']['output_tokens'] ?? 0
        );
    }
}
```

**Step 4: Run tests**

Run: `php artisan test tests/Feature/Jobs/RunGeneralChatMessageJobTest.php`
Expected: PASS

**Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: use ai provider env vars in RunGeneralChatMessageJob"
```

---

## Task 4: Add Provider Selection to GeneralChatBox Livewire Component

**Files:**
- Modify: `app/Livewire/GeneralChatBox.php:22-27,63-86`
- Modify: `resources/views/livewire/general-chat-box.blade.php:1-10`
- Create: `tests/Feature/Livewire/GeneralChatBoxProviderTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Livewire/GeneralChatBoxProviderTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\GeneralChatBox;
use App\Models\AiProvider;
use App\Models\GeneralChat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeneralChatBoxProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_provider_selector(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create(['is_active' => true]);
        $chat = GeneralChat::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('Claude')
            ->assertSee('GLM');
    }

    public function test_it_can_change_provider(): void
    {
        $user = User::factory()->create();
        $claude = AiProvider::factory()->claude()->create();
        $glm = AiProvider::factory()->glm()->create(['is_active' => true]);
        $chat = GeneralChat::factory()->for($user)->create(['ai_provider_id' => $claude->id]);

        Livewire::actingAs($user)
            ->test(GeneralChatBox::class, ['chat' => $chat])
            ->call('setProvider', $glm->id)
            ->assertSet('chat.ai_provider_id', $glm->id);

        expect($chat->fresh()->ai_provider_id)->toBe($glm->id);
    }

    public function test_it_shows_current_provider_badge(): void
    {
        $user = User::factory()->create();
        $glm = AiProvider::factory()->glm()->create(['is_active' => true]);
        $chat = GeneralChat::factory()->for($user)->create(['ai_provider_id' => $glm->id]);

        Livewire::actingAs($user)
            ->test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('GLM (z.ai)');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/GeneralChatBoxProviderTest.php`
Expected: FAIL

**Step 3: Update GeneralChatBox component**

In `app/Livewire/GeneralChatBox.php`, add computed property and method:

```php
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

// Add after existing computed properties:

/**
 * @return EloquentCollection<int, AiProvider>
 */
#[Computed]
public function availableProviders(): EloquentCollection
{
    return AiProvider::where('is_active', true)->get();
}

#[Computed]
public function currentProvider(): ?AiProvider
{
    return $this->chat->aiProvider;
}

public function setProvider(int $providerId): void
{
    $provider = AiProvider::where('is_active', true)->find($providerId);

    if ($provider) {
        $this->chat->update(['ai_provider_id' => $provider->id]);
        $this->chat->refresh();
    }
}
```

**Step 4: Update the Blade view**

In `resources/views/livewire/general-chat-box.blade.php`, update the header section (lines 3-8):

```blade
{{-- Header --}}
<div class="chat-header">
    <div>
        <h2 class="chat-header-title">{{ $this->chatTitle }}</h2>
        <p class="chat-header-subtitle">{{ $chat->working_directory }}</p>
    </div>
    <div class="chat-provider-selector">
        @foreach($this->availableProviders as $provider)
            <button
                wire:click="setProvider({{ $provider->id }})"
                class="chat-provider-btn {{ $this->currentProvider?->id === $provider->id ? 'chat-provider-btn-active' : '' }}"
                @disabled($this->isRunning)
            >
                {{ $provider->display_name }}
            </button>
        @endforeach
    </div>
</div>
```

**Step 5: Add CSS styles**

In `resources/css/filament/chat.css`, add:

```css
.chat-provider-selector {
    display: flex;
    gap: 0.5rem;
}

.chat-provider-btn {
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 9999px;
    border: 1px solid #e5e7eb;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s;
}

.chat-provider-btn:hover:not(:disabled) {
    border-color: #3b82f6;
    color: #3b82f6;
}

.chat-provider-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.chat-provider-btn-active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

.dark .chat-provider-btn {
    border-color: #374151;
    color: #9ca3af;
}

.dark .chat-provider-btn:hover:not(:disabled) {
    border-color: #60a5fa;
    color: #60a5fa;
}

.dark .chat-provider-btn-active {
    background: #2563eb;
    border-color: #2563eb;
    color: white;
}
```

**Step 6: Run tests**

Run: `php artisan test tests/Feature/Livewire/GeneralChatBoxProviderTest.php`
Expected: PASS

**Step 7: Build frontend and commit**

```bash
npm run build
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add provider selector to GeneralChatBox"
```

---

## Task 5: Create AI Provider Settings Page

**Files:**
- Create: `app/Filament/Pages/AiProviderSettings.php`
- Create: `resources/views/filament/pages/ai-provider-settings.blade.php`
- Create: `tests/Feature/Filament/AiProviderSettingsTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Filament/AiProviderSettingsTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AiProviderSettings;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_settings_page(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create();

        $this->actingAs($user)
            ->get('/admin/ai-provider-settings')
            ->assertSuccessful();
    }

    public function test_it_shows_both_providers(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create();

        Livewire::actingAs($user)
            ->test(AiProviderSettings::class)
            ->assertSee('Claude')
            ->assertSee('GLM (z.ai)');
    }

    public function test_it_can_update_glm_api_key(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create();
        $glm = AiProvider::factory()->glm()->create();

        Livewire::actingAs($user)
            ->test(AiProviderSettings::class)
            ->set('glmApiKey', 'new-secret-key')
            ->call('saveGlmSettings')
            ->assertNotified();

        expect($glm->fresh()->api_key)->toBe('new-secret-key');
    }

    public function test_it_can_update_quota_limits(): void
    {
        $user = User::factory()->create();
        $claude = AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create();

        Livewire::actingAs($user)
            ->test(AiProviderSettings::class)
            ->set('claudeQuotaLimit', 5000000)
            ->call('saveClaudeSettings')
            ->assertNotified();

        expect($claude->fresh()->quota_limit)->toBe(5000000);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Filament/AiProviderSettingsTest.php`
Expected: FAIL

**Step 3: Create the Filament page**

Create `app/Filament/Pages/AiProviderSettings.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\AiProvider;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class AiProviderSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'AI Providers';

    protected static ?string $title = 'AI Provider Settings';

    protected static ?string $slug = 'ai-provider-settings';

    protected string $view = 'filament.pages.ai-provider-settings';

    public ?string $glmApiKey = '';

    public ?int $glmQuotaLimit = null;

    public ?int $claudeQuotaLimit = null;

    public function mount(): void
    {
        $glm = $this->getGlmProvider();
        $claude = $this->getClaudeProvider();

        $this->glmApiKey = $glm?->api_key ?? '';
        $this->glmQuotaLimit = $glm?->quota_limit;
        $this->claudeQuotaLimit = $claude?->quota_limit;
    }

    public function getClaudeProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'claude')->first();
    }

    public function getGlmProvider(): ?AiProvider
    {
        return AiProvider::where('name', 'glm')->first();
    }

    public function saveClaudeSettings(): void
    {
        $claude = $this->getClaudeProvider();

        if ($claude) {
            $claude->update([
                'quota_limit' => $this->claudeQuotaLimit,
            ]);
        }

        Notification::make()
            ->title('Claude settings saved')
            ->success()
            ->send();
    }

    public function saveGlmSettings(): void
    {
        $glm = $this->getGlmProvider();

        if ($glm) {
            $glm->update([
                'api_key' => $this->glmApiKey ?: null,
                'quota_limit' => $this->glmQuotaLimit,
                'is_active' => ! empty($this->glmApiKey),
            ]);
        }

        Notification::make()
            ->title('GLM settings saved')
            ->success()
            ->send();
    }

    public function resetQuota(string $providerName): void
    {
        $provider = AiProvider::where('name', $providerName)->first();

        if ($provider) {
            $provider->update([
                'quota_used' => 0,
                'quota_resets_at' => $provider->calculateNextReset(),
            ]);
        }

        Notification::make()
            ->title('Quota reset')
            ->success()
            ->send();
    }
}
```

**Step 4: Create the Blade view**

Create `resources/views/filament/pages/ai-provider-settings.blade.php`:

```blade
<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        {{-- Claude Settings --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/20">
                    <x-heroicon-o-sparkles class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Claude</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Anthropic Claude Code</p>
                </div>
            </div>

            @php $claude = $this->getClaudeProvider(); @endphp

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <p class="mt-1 text-sm {{ $claude?->is_active ? 'text-green-600' : 'text-gray-500' }}">
                        {{ $claude?->is_active ? 'Active (using default credentials)' : 'Inactive' }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Monthly Quota Limit (tokens)</label>
                    <input
                        type="number"
                        wire:model="claudeQuotaLimit"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="10000000"
                    />
                </div>

                @if($claude)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Usage</label>
                        <div class="mt-2">
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ number_format($claude->quota_used) }} tokens</span>
                                <span>{{ number_format($claude->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-2 rounded-full bg-orange-500"
                                    style="width: {{ min(100, $claude->getQuotaPercentage()) }}%"
                                ></div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <button
                        wire:click="saveClaudeSettings"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Save
                    </button>
                    <button
                        wire:click="resetQuota('claude')"
                        wire:confirm="Reset Claude quota to zero?"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Reset Quota
                    </button>
                </div>
            </div>
        </div>

        {{-- GLM Settings --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                    <x-heroicon-o-bolt class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">GLM (z.ai)</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">GLM-4.6 via z.ai</p>
                </div>
            </div>

            @php $glm = $this->getGlmProvider(); @endphp

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <p class="mt-1 text-sm {{ $glm?->is_active ? 'text-green-600' : 'text-yellow-600' }}">
                        {{ $glm?->is_active ? 'Active' : 'Inactive (add API key to enable)' }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">API Key</label>
                    <input
                        type="password"
                        wire:model="glmApiKey"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Enter z.ai API key"
                    />
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Quota Limit (tokens per 5-hour cycle)</label>
                    <input
                        type="number"
                        wire:model="glmQuotaLimit"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="50000000"
                    />
                </div>

                @if($glm)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Usage</label>
                        <div class="mt-2">
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ number_format($glm->quota_used) }} tokens</span>
                                <span>{{ number_format($glm->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-2 rounded-full bg-blue-500"
                                    style="width: {{ min(100, $glm->getQuotaPercentage()) }}%"
                                ></div>
                            </div>
                            @if($glm->quota_resets_at)
                                <p class="text-xs text-gray-500 mt-1">
                                    Resets {{ $glm->quota_resets_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <button
                        wire:click="saveGlmSettings"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Save
                    </button>
                    <button
                        wire:click="resetQuota('glm')"
                        wire:confirm="Reset GLM quota to zero?"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Reset Quota
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```

**Step 5: Make calculateNextReset public**

In `app/Models/AiProvider.php`, change:

```php
protected function calculateNextReset(): \DateTime
```

To:

```php
public function calculateNextReset(): \DateTime
```

**Step 6: Run tests**

Run: `php artisan test tests/Feature/Filament/AiProviderSettingsTest.php`
Expected: PASS

**Step 7: Run Pint and commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add AI Provider settings page in Filament"
```

---

## Task 6: Create Dashboard Quota Widget

**Files:**
- Create: `app/Filament/Widgets/AiProviderQuotaWidget.php`
- Create: `tests/Feature/Filament/AiProviderQuotaWidgetTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Filament/AiProviderQuotaWidgetTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\AiProviderQuotaWidget;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiProviderQuotaWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_widget(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create();

        Livewire::actingAs($user)
            ->test(AiProviderQuotaWidget::class)
            ->assertSuccessful();
    }

    public function test_it_shows_provider_usage(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create([
            'quota_used' => 5000000,
            'quota_limit' => 10000000,
        ]);

        Livewire::actingAs($user)
            ->test(AiProviderQuotaWidget::class)
            ->assertSee('Claude')
            ->assertSee('50'); // 50%
    }

    public function test_it_shows_multiple_providers(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create(['is_active' => true]);

        Livewire::actingAs($user)
            ->test(AiProviderQuotaWidget::class)
            ->assertSee('Claude')
            ->assertSee('GLM');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Filament/AiProviderQuotaWidgetTest.php`
Expected: FAIL

**Step 3: Create the widget**

Run: `php artisan make:filament-widget AiProviderQuotaWidget --no-interaction`

Update `app/Filament/Widgets/AiProviderQuotaWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\AiProvider;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class AiProviderQuotaWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-provider-quota-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, AiProvider>
     */
    public function getProviders(): Collection
    {
        return AiProvider::where('is_active', true)->get();
    }
}
```

**Step 4: Create the widget view**

Create `resources/views/filament/widgets/ai-provider-quota-widget.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            AI Provider Usage
        </x-slot>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach($this->getProviders() as $provider)
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            @if($provider->name === 'claude')
                                <div class="h-8 w-8 rounded-lg bg-orange-100 flex items-center justify-center dark:bg-orange-900/20">
                                    <x-heroicon-o-sparkles class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                </div>
                            @else
                                <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center dark:bg-blue-900/20">
                                    <x-heroicon-o-bolt class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                            @endif
                            <span class="font-medium text-gray-900 dark:text-white">{{ $provider->display_name }}</span>
                        </div>
                        <span class="text-lg font-semibold {{ $provider->getQuotaPercentage() > 80 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format($provider->getQuotaPercentage(), 0) }}%
                        </span>
                    </div>

                    <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700 mb-2">
                        <div
                            class="h-2 rounded-full {{ $provider->name === 'claude' ? 'bg-orange-500' : 'bg-blue-500' }}"
                            style="width: {{ min(100, $provider->getQuotaPercentage()) }}%"
                        ></div>
                    </div>

                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ number_format($provider->quota_used) }} / {{ number_format($provider->quota_limit ?? 0) }} tokens</span>
                        @if($provider->quota_resets_at)
                            <span>Resets {{ $provider->quota_resets_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

**Step 5: Run tests**

Run: `php artisan test tests/Feature/Filament/AiProviderQuotaWidgetTest.php`
Expected: PASS

**Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add AI Provider quota widget to dashboard"
```

---

## Task 7: Add Provider Support to Task Model and Job

**Files:**
- Create: `database/migrations/2025_12_26_000003_add_ai_provider_to_tasks_table.php`
- Modify: `app/Models/Task.php`
- Modify: `app/Jobs/RunClaudeMessageJob.php`
- Modify: `database/factories/TaskFactory.php`
- Modify: `tests/Feature/Jobs/RunClaudeMessageJobTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Jobs/RunClaudeMessageJobTest.php`:

```php
public function test_it_uses_task_provider_env_vars(): void
{
    $provider = AiProvider::factory()->glm()->create();
    $task = Task::factory()->create(['ai_provider_id' => $provider->id]);
    $message = Message::factory()->for($task)->create();

    $job = new RunClaudeMessageJob($task, $message);

    $env = $job->getProviderEnvironment();

    expect($env)->toHaveKey('ANTHROPIC_BASE_URL')
        ->and($env['ANTHROPIC_MODEL'])->toBe('GLM-4.6');
}
```

Add import:

```php
use App\Models\AiProvider;
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Jobs/RunClaudeMessageJobTest.php --filter=provider`
Expected: FAIL

**Step 3: Create migration**

Run: `php artisan make:migration add_ai_provider_to_tasks_table --no-interaction`

Update the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('ai_provider_id')->nullable()->after('site_id')->constrained('ai_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['ai_provider_id']);
            $table->dropColumn('ai_provider_id');
        });
    }
};
```

**Step 4: Update Task model**

In `app/Models/Task.php`, add to `$fillable`:

```php
'ai_provider_id',
```

Add relationship after `messages()`:

```php
public function aiProvider(): BelongsTo
{
    return $this->belongsTo(AiProvider::class);
}
```

Update `booted()`:

```php
protected static function booted(): void
{
    static::creating(function (Task $task) {
        $task->uuid ??= Str::uuid();
        $task->session_id ??= Str::uuid();
        $task->ai_provider_id ??= AiProvider::getDefault()?->id;
    });
}
```

Add import:

```php
use App\Models\AiProvider;
```

**Step 5: Update RunClaudeMessageJob**

In `app/Jobs/RunClaudeMessageJob.php`, add method:

```php
/**
 * @return array<string, string>
 */
public function getProviderEnvironment(): array
{
    $provider = $this->task->aiProvider;

    if (! $provider) {
        return [];
    }

    return $provider->getEnvironmentVariables();
}
```

Update `proc_open` call to include env:

```php
$env = array_merge($_ENV, $_SERVER, $this->getProviderEnvironment());
$process = proc_open($command, $descriptors, $pipes, $workingDir, $env);
```

Add usage tracking after parsing usage (similar to GeneralChat job):

```php
// Track provider usage
if ($this->task->aiProvider) {
    $this->task->aiProvider->incrementUsage(
        $parsed['usage']['input_tokens'] ?? 0,
        $parsed['usage']['output_tokens'] ?? 0
    );
}
```

**Step 6: Update TaskFactory**

In `database/factories/TaskFactory.php`:

```php
'ai_provider_id' => null,
```

Add state method:

```php
public function withProvider(AiProvider $provider): static
{
    return $this->state(['ai_provider_id' => $provider->id]);
}
```

Add import:

```php
use App\Models\AiProvider;
```

**Step 7: Run migration and tests**

Run: `php artisan migrate && php artisan test tests/Feature/Jobs/RunClaudeMessageJobTest.php`
Expected: PASS

**Step 8: Run Pint and commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add ai_provider support to Task model and job"
```

---

## Task 8: Add Provider Selection to TaskChat Component

**Files:**
- Modify: `app/Livewire/TaskChat.php`
- Modify: `resources/views/livewire/task-chat.blade.php`
- Create: `tests/Feature/Livewire/TaskChatProviderTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Livewire/TaskChatProviderTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TaskChat;
use App\Models\AiProvider;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskChatProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_provider_selector(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create(['is_active' => true]);
        $task = Task::factory()->create();

        Livewire::actingAs($user)
            ->test(TaskChat::class, ['task' => $task])
            ->assertSee('Claude')
            ->assertSee('GLM');
    }

    public function test_it_can_change_provider(): void
    {
        $user = User::factory()->create();
        $claude = AiProvider::factory()->claude()->create();
        $glm = AiProvider::factory()->glm()->create(['is_active' => true]);
        $task = Task::factory()->create(['ai_provider_id' => $claude->id]);

        Livewire::actingAs($user)
            ->test(TaskChat::class, ['task' => $task])
            ->call('setProvider', $glm->id)
            ->assertSet('task.ai_provider_id', $glm->id);

        expect($task->fresh()->ai_provider_id)->toBe($glm->id);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/TaskChatProviderTest.php`
Expected: FAIL

**Step 3: Update TaskChat component**

In `app/Livewire/TaskChat.php`, add the same provider methods as GeneralChatBox:

```php
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @return EloquentCollection<int, AiProvider>
 */
#[Computed]
public function availableProviders(): EloquentCollection
{
    return AiProvider::where('is_active', true)->get();
}

#[Computed]
public function currentProvider(): ?AiProvider
{
    return $this->task->aiProvider;
}

public function setProvider(int $providerId): void
{
    $provider = AiProvider::where('is_active', true)->find($providerId);

    if ($provider) {
        $this->task->update(['ai_provider_id' => $provider->id]);
        $this->task->refresh();
    }
}
```

**Step 4: Update the task-chat Blade view**

In `resources/views/livewire/task-chat.blade.php`, add provider selector to header (similar pattern to general-chat-box):

```blade
<div class="chat-provider-selector">
    @foreach($this->availableProviders as $provider)
        <button
            wire:click="setProvider({{ $provider->id }})"
            class="chat-provider-btn {{ $this->currentProvider?->id === $provider->id ? 'chat-provider-btn-active' : '' }}"
            @disabled($this->isRunning)
        >
            {{ $provider->display_name }}
        </button>
    @endforeach
</div>
```

**Step 5: Run tests**

Run: `php artisan test tests/Feature/Livewire/TaskChatProviderTest.php`
Expected: PASS

**Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add provider selector to TaskChat component"
```

---

## Task 9: Run Full Test Suite and Final Verification

**Step 1: Run all tests**

```bash
php artisan test
```

Expected: All tests pass

**Step 2: Run Pint on entire codebase**

```bash
vendor/bin/pint
```

**Step 3: Manual verification checklist**

1. [ ] Visit `/admin` dashboard - quota widget visible
2. [ ] Visit `/admin/ai-provider-settings` - both providers shown
3. [ ] Add GLM API key - provider becomes active
4. [ ] Create new GeneralChat - provider selector visible
5. [ ] Switch providers - persists correctly
6. [ ] Send message with GLM selected - uses GLM env vars
7. [ ] Check quota usage updates after message

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete multi-provider AI support with quota tracking"
```

---

## Summary

This implementation adds:
1. **AiProvider model** - Stores Claude and GLM configs with encrypted API keys
2. **Provider selection** - Per chat/task provider switching via UI toggle
3. **Quota tracking** - Tracks token usage per provider with configurable limits
4. **Dashboard widget** - Visual quota display with progress bars
5. **Settings page** - Configure API keys and quota limits
6. **Environment injection** - Jobs set correct env vars for selected provider
