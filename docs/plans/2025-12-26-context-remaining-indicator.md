# Context Remaining Indicator Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Show a visual progress bar indicating how much context window remains in each chat.

**Architecture:** Add `context_window` field to AiProvider with 200K default. Calculate context used from the last assistant message's `tokens_in` value. Display as a color-coded progress bar in the chat header.

**Tech Stack:** Laravel 12, Livewire 3, Tailwind CSS 4, PHP 8.4

---

## Task 1: Create Migration

**Files:**
- Create: `database/migrations/2025_12_26_200000_add_context_window_to_ai_providers_table.php`

**Step 1: Create the migration file**

```bash
php artisan make:migration add_context_window_to_ai_providers_table --table=ai_providers
```

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->unsignedInteger('context_window')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn('context_window');
        });
    }
};
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations
git commit -m "feat: add context_window column to ai_providers table"
```

---

## Task 2: Update AiProvider Model

**Files:**
- Modify: `app/Models/AiProvider.php`

**Step 1: Add context_window to fillable array**

Find the `$fillable` array and add `'context_window'` after `'model'`:

```php
protected $fillable = [
    'name',
    'display_name',
    'base_url',
    'api_key',
    'model',
    'context_window',
    'is_active',
    // ... rest
];
```

**Step 2: Add getContextWindow method**

Add after the `calculateNextReset()` method:

```php
public function getContextWindow(): int
{
    return $this->context_window ?? 200000;
}
```

**Step 3: Run Pint**

```bash
vendor/bin/pint app/Models/AiProvider.php
```

**Step 4: Commit**

```bash
git add app/Models/AiProvider.php
git commit -m "feat: add getContextWindow method with 200K default"
```

---

## Task 3: Add Context Computed Properties to TaskChat

**Files:**
- Modify: `app/Livewire/TaskChat.php`

**Step 1: Add contextUsed computed property**

Add after the `defaultEnvConfig()` method (around line 127):

```php
#[Computed]
public function contextUsed(): int
{
    $lastAssistantMessage = $this->task->messages()
        ->where('role', MessageRole::Assistant)
        ->whereNotNull('tokens_in')
        ->latest()
        ->first();

    return $lastAssistantMessage?->tokens_in ?? 0;
}
```

**Step 2: Add contextLimit computed property**

```php
#[Computed]
public function contextLimit(): int
{
    return $this->task->aiProvider?->getContextWindow() ?? 200000;
}
```

**Step 3: Add contextPercentage computed property**

```php
#[Computed]
public function contextPercentage(): float
{
    if ($this->contextLimit === 0) {
        return 0;
    }

    return ($this->contextUsed / $this->contextLimit) * 100;
}
```

**Step 4: Add contextColor computed property**

```php
#[Computed]
public function contextColor(): string
{
    $percentage = $this->contextPercentage;

    if ($percentage >= 80) {
        return 'bg-red-500';
    }

    if ($percentage >= 60) {
        return 'bg-amber-500';
    }

    return 'bg-green-500';
}
```

**Step 5: Run Pint**

```bash
vendor/bin/pint app/Livewire/TaskChat.php
```

**Step 6: Commit**

```bash
git add app/Livewire/TaskChat.php
git commit -m "feat: add context tracking computed properties to TaskChat"
```

---

## Task 4: Add Context Computed Properties to GeneralChatBox

**Files:**
- Modify: `app/Livewire/GeneralChatBox.php`

**Step 1: Add the same four computed properties**

Add after the `currentProvider()` method (around line 88):

```php
#[Computed]
public function contextUsed(): int
{
    $lastAssistantMessage = $this->chat->messages()
        ->where('role', MessageRole::Assistant)
        ->whereNotNull('tokens_in')
        ->latest()
        ->first();

    return $lastAssistantMessage?->tokens_in ?? 0;
}

#[Computed]
public function contextLimit(): int
{
    return $this->chat->aiProvider?->getContextWindow() ?? 200000;
}

#[Computed]
public function contextPercentage(): float
{
    if ($this->contextLimit === 0) {
        return 0;
    }

    return ($this->contextUsed / $this->contextLimit) * 100;
}

#[Computed]
public function contextColor(): string
{
    $percentage = $this->contextPercentage;

    if ($percentage >= 80) {
        return 'bg-red-500';
    }

    if ($percentage >= 60) {
        return 'bg-amber-500';
    }

    return 'bg-green-500';
}
```

**Step 2: Run Pint**

```bash
vendor/bin/pint app/Livewire/GeneralChatBox.php
```

**Step 3: Commit**

```bash
git add app/Livewire/GeneralChatBox.php
git commit -m "feat: add context tracking computed properties to GeneralChatBox"
```

---

## Task 5: Add Progress Bar to TaskChat Blade Template

**Files:**
- Modify: `resources/views/livewire/task-chat.blade.php`

**Step 1: Add progress bar after provider selector**

Find line 19 (the closing `</div>` of `chat-provider-selector`) and add the progress bar right after it:

```blade
            </div>
            {{-- Context Usage Indicator --}}
            <div
                class="flex items-center gap-2"
                title="{{ number_format($this->contextUsed) }} tokens used of {{ number_format($this->contextLimit) }} ({{ number_format($this->contextPercentage, 1) }}%)"
            >
                <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="{{ $this->contextColor }} h-full transition-all duration-300"
                        style="width: {{ min($this->contextPercentage, 100) }}%"
                    ></div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ number_format($this->contextUsed / 1000, 0) }}K / {{ number_format($this->contextLimit / 1000, 0) }}K
                </span>
            </div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/task-chat.blade.php
git commit -m "feat: add context usage progress bar to TaskChat"
```

---

## Task 6: Add Progress Bar to GeneralChatBox Blade Template

**Files:**
- Modify: `resources/views/livewire/general-chat-box.blade.php`

**Step 1: Add progress bar after provider selector**

Find line 33 (the closing `</div>` of `chat-provider-selector`) and add the same progress bar:

```blade
            </div>
            {{-- Context Usage Indicator --}}
            <div
                class="flex items-center gap-2"
                title="{{ number_format($this->contextUsed) }} tokens used of {{ number_format($this->contextLimit) }} ({{ number_format($this->contextPercentage, 1) }}%)"
            >
                <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="{{ $this->contextColor }} h-full transition-all duration-300"
                        style="width: {{ min($this->contextPercentage, 100) }}%"
                    ></div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ number_format($this->contextUsed / 1000, 0) }}K / {{ number_format($this->contextLimit / 1000, 0) }}K
                </span>
            </div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/general-chat-box.blade.php
git commit -m "feat: add context usage progress bar to GeneralChatBox"
```

---

## Task 7: Add Context Window Field to AiProvider Settings

**Files:**
- Modify: `app/Filament/Pages/AiProviderSettings.php`
- Modify: `resources/views/filament/pages/ai-provider-settings.blade.php`

**Step 1: Add properties to AiProviderSettings.php**

Add after line 29 (`public ?int $claudeQuotaLimit = null;`):

```php
public ?int $claudeContextWindow = null;

public ?int $glmContextWindow = null;
```

**Step 2: Update mount() to load context window values**

Update the mount method to include:

```php
public function mount(): void
{
    $glm = $this->getGlmProvider();
    $claude = $this->getClaudeProvider();

    $this->glmApiKey = $glm?->api_key ?? '';
    $this->glmQuotaLimit = $glm?->quota_limit;
    $this->glmContextWindow = $glm?->context_window;
    $this->claudeQuotaLimit = $claude?->quota_limit;
    $this->claudeContextWindow = $claude?->context_window;
}
```

**Step 3: Update saveClaudeSettings() to save context window**

```php
public function saveClaudeSettings(): void
{
    $claude = $this->getClaudeProvider();

    if ($claude) {
        $claude->update([
            'quota_limit' => $this->claudeQuotaLimit,
            'context_window' => $this->claudeContextWindow,
        ]);
    }

    Notification::make()
        ->title('Claude settings saved')
        ->success()
        ->send();
}
```

**Step 4: Update saveGlmSettings() to save context window**

```php
public function saveGlmSettings(): void
{
    $glm = $this->getGlmProvider();

    if ($glm) {
        $glm->update([
            'api_key' => $this->glmApiKey ?: null,
            'quota_limit' => $this->glmQuotaLimit,
            'context_window' => $this->glmContextWindow,
            'is_active' => ! empty($this->glmApiKey),
        ]);
    }

    Notification::make()
        ->title('GLM settings saved')
        ->success()
        ->send();
}
```

**Step 5: Add context window input to Claude section in blade template**

After the Monthly Quota Limit input (around line 38), add:

```blade
                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Context Window (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="claudeContextWindow"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="200000 (default)"
                    />
                </div>
```

**Step 6: Add context window input to GLM section in blade template**

After the Quota Limit input (around line 113), add:

```blade
                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Context Window (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="glmContextWindow"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="200000 (default)"
                    />
                </div>
```

**Step 7: Run Pint**

```bash
vendor/bin/pint app/Filament/Pages/AiProviderSettings.php
```

**Step 8: Commit**

```bash
git add app/Filament/Pages/AiProviderSettings.php resources/views/filament/pages/ai-provider-settings.blade.php
git commit -m "feat: add context window setting to AI provider settings"
```

---

## Task 8: Final Verification

**Step 1: Run Pint on all files**

```bash
vendor/bin/pint --dirty
```

**Step 2: Test in browser**

1. Navigate to a task chat with messages
2. Verify progress bar appears in header after provider buttons
3. Verify tooltip shows token details on hover
4. Verify bar is green (if < 60% used)
5. Navigate to General Chat and verify same behavior
6. Navigate to AI Provider Settings
7. Verify Context Window field appears for both providers

**Step 3: Final commit if any changes**

```bash
git add -A
git commit -m "chore: final cleanup for context indicator feature"
```
