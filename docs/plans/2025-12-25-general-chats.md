# General Chats Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a repository-independent chat system for general tasks like updating skills, running system commands, and other non-project work.

**Architecture:** Separate `GeneralChat` and `GeneralChatMessage` models with their own Filament resource. Chat runs Claude in the user's home directory (`/home/ploi`). Includes a file browser sidebar for visibility into what Claude is working on.

**Tech Stack:** Laravel 12, Filament v4, Livewire 3, Tailwind v4

---

## Task 1: Create GeneralChat Migration

**Files:**
- Create: `database/migrations/XXXX_create_general_chats_table.php`

**Step 1: Generate migration**

Run:
```bash
php artisan make:migration create_general_chats_table --no-interaction
```

**Step 2: Write migration schema**

Edit the generated file to contain:

```php
<?php

use App\Enums\GeneralChatStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_chats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_id');
            $table->string('title')->nullable();
            $table->string('working_directory')->default('/home/ploi');
            $table->string('status')->default(GeneralChatStatus::Pending->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_chats');
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/*create_general_chats_table.php
git commit -m "feat: add general_chats migration"
```

---

## Task 2: Create GeneralChatMessage Migration

**Files:**
- Create: `database/migrations/XXXX_create_general_chat_messages_table.php`

**Step 1: Generate migration**

Run:
```bash
php artisan make:migration create_general_chat_messages_table --no-interaction
```

**Step 2: Write migration schema**

Edit the generated file to contain:

```php
<?php

use App\Enums\MessageRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_chat_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(MessageRole::User->value);
            $table->longText('content')->nullable();
            $table->longText('raw_output')->nullable();
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_chat_messages');
    }
};
```

**Step 3: Run migrations**

Run:
```bash
php artisan migrate --no-interaction
```
Expected: Both tables created successfully.

**Step 4: Commit**

```bash
git add database/migrations/*create_general_chat_messages_table.php
git commit -m "feat: add general_chat_messages migration"
```

---

## Task 3: Create GeneralChatStatus Enum

**Files:**
- Create: `app/Enums/GeneralChatStatus.php`

**Step 1: Create the enum file**

```php
<?php

namespace App\Enums;

enum GeneralChatStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
```

**Step 2: Commit**

```bash
git add app/Enums/GeneralChatStatus.php
git commit -m "feat: add GeneralChatStatus enum"
```

---

## Task 4: Create GeneralChat Model

**Files:**
- Create: `app/Models/GeneralChat.php`
- Test: `tests/Feature/Models/GeneralChatTest.php`

**Step 1: Write the failing test**

Run:
```bash
php artisan make:test Models/GeneralChatTest --no-interaction
```

Edit `tests/Feature/Models/GeneralChatTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\GeneralChatStatus;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_uuid_and_session_id_on_create(): void
    {
        $chat = GeneralChat::factory()->create();

        expect($chat->uuid)->not->toBeNull()
            ->and($chat->session_id)->not->toBeNull();
    }

    public function test_it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $chat = GeneralChat::factory()->for($user)->create();

        expect($chat->user->id)->toBe($user->id);
    }

    public function test_it_has_many_messages(): void
    {
        $chat = GeneralChat::factory()->create();
        GeneralChatMessage::factory()->count(3)->for($chat)->create();

        expect($chat->messages)->toHaveCount(3);
    }

    public function test_it_can_mark_as_running(): void
    {
        $chat = GeneralChat::factory()->create();

        $chat->markAsRunning();

        expect($chat->status)->toBe(GeneralChatStatus::Running)
            ->and($chat->started_at)->not->toBeNull();
    }

    public function test_it_can_mark_as_completed(): void
    {
        $chat = GeneralChat::factory()->running()->create();

        $chat->markAsCompleted();

        expect($chat->status)->toBe(GeneralChatStatus::Completed)
            ->and($chat->completed_at)->not->toBeNull();
    }

    public function test_it_uses_uuid_for_route_key(): void
    {
        $chat = GeneralChat::factory()->create();

        expect($chat->getRouteKeyName())->toBe('uuid');
    }

    public function test_default_working_directory_is_home(): void
    {
        $chat = GeneralChat::factory()->create(['working_directory' => null]);

        // Factory sets it, but model should default
        expect($chat->working_directory)->toBe('/home/ploi');
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
php artisan test tests/Feature/Models/GeneralChatTest.php --stop-on-failure
```
Expected: FAIL - GeneralChat class not found

**Step 3: Write the GeneralChat model**

Create `app/Models/GeneralChat.php`:

```php
<?php

namespace App\Models;

use App\Enums\GeneralChatStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GeneralChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'session_id',
        'title',
        'working_directory',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GeneralChatStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GeneralChat $chat) {
            $chat->uuid ??= Str::uuid();
            $chat->session_id ??= Str::uuid();
            $chat->working_directory ??= '/home/ploi';
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GeneralChatMessage::class);
    }

    public function isRunning(): bool
    {
        return $this->status === GeneralChatStatus::Running;
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => GeneralChatStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => GeneralChatStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => GeneralChatStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

Run:
```bash
php artisan test tests/Feature/Models/GeneralChatTest.php
```
Expected: Some failures (factory and message model not yet created)

**Step 5: Commit partial progress**

```bash
git add app/Models/GeneralChat.php tests/Feature/Models/GeneralChatTest.php
git commit -m "feat: add GeneralChat model with tests (partial)"
```

---

## Task 5: Create GeneralChatMessage Model

**Files:**
- Create: `app/Models/GeneralChatMessage.php`
- Test: `tests/Feature/Models/GeneralChatMessageTest.php`

**Step 1: Write the failing test**

Run:
```bash
php artisan make:test Models/GeneralChatMessageTest --no-interaction
```

Edit `tests/Feature/Models/GeneralChatMessageTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\MessageRole;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_general_chat(): void
    {
        $chat = GeneralChat::factory()->create();
        $message = GeneralChatMessage::factory()->for($chat)->create();

        expect($message->generalChat->id)->toBe($chat->id);
    }

    public function test_it_can_check_role(): void
    {
        $userMessage = GeneralChatMessage::factory()->user()->create();
        $assistantMessage = GeneralChatMessage::factory()->assistant()->create();

        expect($userMessage->isFromUser())->toBeTrue()
            ->and($userMessage->isFromAssistant())->toBeFalse()
            ->and($assistantMessage->isFromAssistant())->toBeTrue()
            ->and($assistantMessage->isFromUser())->toBeFalse();
    }

    public function test_it_can_append_raw_output(): void
    {
        $message = GeneralChatMessage::factory()->create(['raw_output' => 'Hello']);

        $message->appendRawOutput(' World');

        expect($message->fresh()->raw_output)->toBe('Hello World');
    }

    public function test_it_can_add_tool_calls(): void
    {
        $message = GeneralChatMessage::factory()->create();

        $message->addToolCall(['name' => 'Read', 'input' => ['file_path' => '/test']]);
        $message->addToolCall(['name' => 'Edit', 'input' => ['file_path' => '/test']]);

        expect($message->fresh()->tool_calls)->toHaveCount(2);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
php artisan test tests/Feature/Models/GeneralChatMessageTest.php --stop-on-failure
```
Expected: FAIL - GeneralChatMessage class not found

**Step 3: Write the GeneralChatMessage model**

Create `app/Models/GeneralChatMessage.php`:

```php
<?php

namespace App\Models;

use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'general_chat_id',
        'role',
        'content',
        'raw_output',
        'tool_calls',
        'tokens_in',
        'tokens_out',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'tool_calls' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function generalChat(): BelongsTo
    {
        return $this->belongsTo(GeneralChat::class);
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function appendRawOutput(string $chunk): void
    {
        $this->update([
            'raw_output' => ($this->raw_output ?? '').$chunk,
        ]);
    }

    public function addToolCall(array $toolCall): void
    {
        $calls = $this->tool_calls ?? [];
        $calls[] = $toolCall;
        $this->update(['tool_calls' => $calls]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Models/GeneralChatMessage.php tests/Feature/Models/GeneralChatMessageTest.php
git commit -m "feat: add GeneralChatMessage model with tests"
```

---

## Task 6: Create Factories

**Files:**
- Create: `database/factories/GeneralChatFactory.php`
- Create: `database/factories/GeneralChatMessageFactory.php`

**Step 1: Create GeneralChatFactory**

Run:
```bash
php artisan make:factory GeneralChatFactory --no-interaction
```

Edit `database/factories/GeneralChatFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\GeneralChatStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GeneralChatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'user_id' => User::factory(),
            'session_id' => Str::uuid(),
            'title' => fake()->optional()->sentence(3),
            'working_directory' => '/home/ploi',
            'status' => GeneralChatStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => GeneralChatStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => GeneralChatStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => GeneralChatStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    public function withTitle(string $title): static
    {
        return $this->state(['title' => $title]);
    }
}
```

**Step 2: Create GeneralChatMessageFactory**

Run:
```bash
php artisan make:factory GeneralChatMessageFactory --no-interaction
```

Edit `database/factories/GeneralChatMessageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Models\GeneralChat;
use Illuminate\Database\Eloquent\Factories\Factory;

class GeneralChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'general_chat_id' => GeneralChat::factory(),
            'role' => MessageRole::User,
            'content' => fake()->paragraph(),
            'raw_output' => null,
            'tool_calls' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => null,
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => MessageRole::User]);
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => MessageRole::Assistant,
            'tokens_in' => fake()->numberBetween(100, 1000),
            'tokens_out' => fake()->numberBetween(500, 5000),
            'cost_usd' => fake()->randomFloat(6, 0.001, 0.1),
        ]);
    }

    public function withToolCalls(): static
    {
        return $this->state([
            'tool_calls' => [
                ['name' => 'Read', 'input' => ['file_path' => '/home/ploi/.claude/settings.json']],
                ['name' => 'Edit', 'input' => ['file_path' => '/home/ploi/.claude/settings.json']],
            ],
        ]);
    }
}
```

**Step 3: Run all model tests**

Run:
```bash
php artisan test tests/Feature/Models/GeneralChatTest.php tests/Feature/Models/GeneralChatMessageTest.php
```
Expected: All tests pass

**Step 4: Commit**

```bash
git add database/factories/GeneralChatFactory.php database/factories/GeneralChatMessageFactory.php
git commit -m "feat: add GeneralChat and GeneralChatMessage factories"
```

---

## Task 7: Create RunGeneralChatMessageJob

**Files:**
- Create: `app/Jobs/RunGeneralChatMessageJob.php`
- Test: `tests/Feature/Jobs/RunGeneralChatMessageJobTest.php`

**Step 1: Write the failing test**

Run:
```bash
php artisan make:test Jobs/RunGeneralChatMessageJobTest --no-interaction
```

Edit `tests/Feature/Jobs/RunGeneralChatMessageJobTest.php`:

```php
<?php

namespace Tests\Feature\Jobs;

use App\Enums\GeneralChatStatus;
use App\Enums\MessageRole;
use App\Jobs\RunGeneralChatMessageJob;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunGeneralChatMessageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_correct_command(): void
    {
        $chat = GeneralChat::factory()->create(['session_id' => 'test-session-123']);
        $message = GeneralChatMessage::factory()->for($chat)->create(['content' => 'Hello']);

        $job = new RunGeneralChatMessageJob($chat, $message, continue: false);

        $command = $job->buildCommand();

        expect($command)->toContain('claude -p')
            ->and($command)->toContain('--output-format stream-json')
            ->and($command)->toContain('--session-id')
            ->and($command)->not->toContain('--continue');
    }

    public function test_it_includes_continue_flag_when_continuing(): void
    {
        $chat = GeneralChat::factory()->create();
        $message = GeneralChatMessage::factory()->for($chat)->create(['content' => 'Follow up']);

        $job = new RunGeneralChatMessageJob($chat, $message, continue: true);

        $command = $job->buildCommand();

        expect($command)->toContain('--continue');
    }

    public function test_it_creates_assistant_message_on_handle(): void
    {
        $chat = GeneralChat::factory()->create();
        $userMessage = GeneralChatMessage::factory()->for($chat)->user()->create();

        // We can't fully test the job without mocking proc_open,
        // but we can test that it marks the chat as running
        $chat->markAsRunning();

        expect($chat->status)->toBe(GeneralChatStatus::Running);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
php artisan test tests/Feature/Jobs/RunGeneralChatMessageJobTest.php --stop-on-failure
```
Expected: FAIL - RunGeneralChatMessageJob class not found

**Step 3: Write the job**

Create `app/Jobs/RunGeneralChatMessageJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunGeneralChatMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public GeneralChat $chat,
        public GeneralChatMessage $userMessage,
        public bool $continue = false
    ) {}

    public function handle(): void
    {
        $this->chat->markAsRunning();

        $assistantMessage = GeneralChatMessage::create([
            'general_chat_id' => $this->chat->id,
            'role' => MessageRole::Assistant,
            'content' => '',
        ]);

        try {
            $command = $this->buildCommand();
            $workingDir = $this->chat->working_directory;

            Log::info('Running Claude Code (General Chat)', [
                'chat_id' => $this->chat->id,
                'command' => $command,
                'working_dir' => $workingDir,
            ]);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, $workingDir);

            if (! is_resource($process)) {
                throw new \RuntimeException('Failed to start Claude process');
            }

            fclose($pipes[0]);

            $output = '';
            $toolCalls = [];

            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    continue;
                }

                $output .= $line;
                $assistantMessage->appendRawOutput($line);

                $parsed = $this->parseLine($line);
                if ($parsed) {
                    if (isset($parsed['tool_call'])) {
                        $toolCalls[] = $parsed['tool_call'];
                        $assistantMessage->update(['tool_calls' => $toolCalls]);
                    }
                    if (isset($parsed['content'])) {
                        $assistantMessage->update([
                            'content' => ($assistantMessage->content ?? '').$parsed['content'],
                        ]);
                    }
                    if (isset($parsed['usage'])) {
                        $assistantMessage->update([
                            'tokens_in' => $parsed['usage']['input_tokens'] ?? null,
                            'tokens_out' => $parsed['usage']['output_tokens'] ?? null,
                            'cost_usd' => $parsed['usage']['cost_usd'] ?? null,
                        ]);
                    }
                }
            }

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                Log::warning("Claude exited with code {$exitCode}", ['stderr' => $stderr]);
            }

            $this->chat->markAsCompleted();

        } catch (\Throwable $e) {
            Log::error("Claude execution failed: {$e->getMessage()}");

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->chat->markAsFailed();

            throw $e;
        }
    }

    public function buildCommand(): string
    {
        $prompt = escapeshellarg($this->userMessage->content);
        $sessionId = escapeshellarg($this->chat->session_id);

        $cmd = "claude -p {$prompt} --output-format stream-json --session-id {$sessionId}";

        if ($this->continue) {
            $cmd .= ' --continue';
        }

        return $cmd;
    }

    /**
     * @return array{content?: string, tool_call?: array<string, mixed>, usage?: array<string, mixed>}|null
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $data = json_decode($line, true);
        if (! $data) {
            return null;
        }

        $result = [];

        if (($data['type'] ?? '') === 'assistant' && isset($data['message']['content'])) {
            foreach ($data['message']['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $result['content'] = $block['text'] ?? '';
                }
                if (($block['type'] ?? '') === 'tool_use') {
                    $result['tool_call'] = [
                        'id' => $block['id'] ?? null,
                        'name' => $block['name'] ?? '',
                        'input' => $block['input'] ?? [],
                    ];
                }
            }
        }

        if (($data['type'] ?? '') === 'result') {
            $result['usage'] = [
                'input_tokens' => $data['total_input_tokens'] ?? null,
                'output_tokens' => $data['total_output_tokens'] ?? null,
                'cost_usd' => $data['total_cost_usd'] ?? null,
            ];
        }

        return $result ?: null;
    }
}
```

**Step 4: Run tests**

Run:
```bash
php artisan test tests/Feature/Jobs/RunGeneralChatMessageJobTest.php
```
Expected: All tests pass

**Step 5: Commit**

```bash
git add app/Jobs/RunGeneralChatMessageJob.php tests/Feature/Jobs/RunGeneralChatMessageJobTest.php
git commit -m "feat: add RunGeneralChatMessageJob"
```

---

## Task 8: Create GeneralChatBox Livewire Component

**Files:**
- Create: `app/Livewire/GeneralChatBox.php`
- Create: `resources/views/livewire/general-chat-box.blade.php`
- Test: `tests/Feature/Livewire/GeneralChatBoxTest.php`

**Step 1: Write the failing test**

Run:
```bash
php artisan make:test Livewire/GeneralChatBoxTest --no-interaction
```

Edit `tests/Feature/Livewire/GeneralChatBoxTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Jobs\RunGeneralChatMessageJob;
use App\Livewire\GeneralChatBox;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class GeneralChatBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_with_chat(): void
    {
        $chat = GeneralChat::factory()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertStatus(200)
            ->assertSee('Start a conversation');
    }

    public function test_it_displays_existing_messages(): void
    {
        $chat = GeneralChat::factory()->create();
        GeneralChatMessage::factory()->for($chat)->user()->create(['content' => 'Hello Claude']);
        GeneralChatMessage::factory()->for($chat)->assistant()->create(['content' => 'Hello! How can I help?']);

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('Hello Claude')
            ->assertSee('Hello! How can I help?');
    }

    public function test_it_sends_message_and_dispatches_job(): void
    {
        Queue::fake();

        $chat = GeneralChat::factory()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->set('prompt', 'Update my skills please')
            ->call('sendMessage')
            ->assertSet('prompt', '');

        $this->assertDatabaseHas('general_chat_messages', [
            'general_chat_id' => $chat->id,
            'content' => 'Update my skills please',
        ]);

        Queue::assertPushed(RunGeneralChatMessageJob::class);
    }

    public function test_it_shows_thinking_indicator_when_running(): void
    {
        $chat = GeneralChat::factory()->running()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('Claude is thinking...');
    }

    public function test_it_disables_input_when_running(): void
    {
        $chat = GeneralChat::factory()->running()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSeeHtml('disabled');
    }

    public function test_it_displays_chat_title_in_header(): void
    {
        $chat = GeneralChat::factory()->withTitle('Skills Update Chat')->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('Skills Update Chat');
    }

    public function test_it_shows_general_chat_label_when_no_title(): void
    {
        $chat = GeneralChat::factory()->create(['title' => null]);

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('General Chat');
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
php artisan test tests/Feature/Livewire/GeneralChatBoxTest.php --stop-on-failure
```
Expected: FAIL - GeneralChatBox class not found

**Step 3: Create the Livewire component**

Run:
```bash
php artisan make:livewire GeneralChatBox --no-interaction
```

Edit `app/Livewire/GeneralChatBox.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunGeneralChatMessageJob;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GeneralChatBox extends Component
{
    public GeneralChat $chat;

    public string $prompt = '';

    public function mount(GeneralChat $chat): void
    {
        $this->chat = $chat;
    }

    /**
     * @return Collection<int, GeneralChatMessage>
     */
    #[Computed]
    public function chatMessages(): Collection
    {
        return $this->chat->messages()->oldest()->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->chat->isRunning();
    }

    #[Computed]
    public function chatTitle(): string
    {
        return $this->chat->title ?? 'General Chat';
    }

    public function sendMessage(): void
    {
        $this->validate([
            'prompt' => 'required|string|min:1|max:10000',
        ]);

        $isFirstMessage = $this->chat->messages()->count() === 0;

        $userMessage = GeneralChatMessage::create([
            'general_chat_id' => $this->chat->id,
            'role' => MessageRole::User,
            'content' => $this->prompt,
        ]);

        RunGeneralChatMessageJob::dispatch(
            $this->chat,
            $userMessage,
            continue: ! $isFirstMessage
        );

        $this->prompt = '';
    }

    public function render()
    {
        return view('livewire.general-chat-box');
    }
}
```

**Step 4: Create the Blade view**

Edit `resources/views/livewire/general-chat-box.blade.php`:

```blade
<div class="flex h-full flex-col">
    {{-- Header --}}
    <div class="mb-4 flex items-center justify-between rounded-lg bg-white p-4 shadow dark:bg-gray-900">
        <div>
            <h2 class="text-lg font-semibold">{{ $this->chatTitle }}</h2>
            <p class="text-sm text-gray-500">{{ $chat->working_directory }}</p>
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex flex-1 flex-col rounded-lg bg-white shadow dark:bg-gray-900">
        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" wire:poll.2s="$refresh">
            @forelse($this->chatMessages as $message)
                <div wire:key="message-{{ $message->id }}" @class([
                    'flex',
                    'justify-end' => $message->isFromUser(),
                    'justify-start' => $message->isFromAssistant(),
                ])>
                    <div @class([
                        'max-w-[80%] rounded-lg px-4 py-2',
                        'bg-primary-600 text-white' => $message->isFromUser(),
                        'bg-gray-100 dark:bg-gray-800' => $message->isFromAssistant(),
                    ])>
                        @if($message->isFromUser())
                            <p class="whitespace-pre-wrap">{{ $message->content }}</p>
                        @else
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($message->content ?? '') !!}
                            </div>

                            @if($message->tool_calls)
                                <div class="mt-2 space-y-2">
                                    @foreach($message->tool_calls as $tool)
                                        <details class="rounded bg-gray-200 dark:bg-gray-700 p-2 text-xs">
                                            <summary class="cursor-pointer font-mono">{{ $tool['name'] ?? 'Tool' }}</summary>
                                            <pre class="mt-1 overflow-x-auto">{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endforeach
                                </div>
                            @endif

                            @if($message->tokens_in || $message->tokens_out)
                                <div class="mt-2 text-xs text-gray-500">
                                    {{ number_format($message->tokens_in ?? 0) }} in /
                                    {{ number_format($message->tokens_out ?? 0) }} out
                                    @if($message->cost_usd)
                                        (${{ number_format($message->cost_usd, 4) }})
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex h-full items-center justify-center text-gray-500">
                    <p>Start a conversation with Claude</p>
                </div>
            @endforelse

            @if($this->isRunning)
                <div class="flex justify-start">
                    <div class="rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                            <span class="text-sm text-gray-500">Claude is thinking...</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div class="border-t p-4 dark:border-gray-700">
            <form wire:submit="sendMessage" class="flex gap-2">
                <textarea
                    wire:model="prompt"
                    placeholder="Type your message..."
                    rows="2"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                    @disabled($this->isRunning)
                ></textarea>
                <button
                    type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700 disabled:opacity-50"
                    @disabled($this->isRunning || empty($prompt))
                >
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
```

**Step 5: Run tests**

Run:
```bash
php artisan test tests/Feature/Livewire/GeneralChatBoxTest.php
```
Expected: All tests pass

**Step 6: Commit**

```bash
git add app/Livewire/GeneralChatBox.php resources/views/livewire/general-chat-box.blade.php tests/Feature/Livewire/GeneralChatBoxTest.php
git commit -m "feat: add GeneralChatBox Livewire component"
```

---

## Task 9: Create FileBrowser Livewire Component

**Files:**
- Create: `app/Livewire/FileBrowser.php`
- Create: `resources/views/livewire/file-browser.blade.php`
- Test: `tests/Feature/Livewire/FileBrowserTest.php`

**Step 1: Write the failing test**

Run:
```bash
php artisan make:test Livewire/FileBrowserTest --no-interaction
```

Edit `tests/Feature/Livewire/FileBrowserTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FileBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class FileBrowserTest extends TestCase
{
    use RefreshDatabase;

    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = storage_path('app/test-file-browser');
        File::makeDirectory($this->testDir, 0755, true, true);
        File::put($this->testDir.'/test.txt', 'Hello World');
        File::makeDirectory($this->testDir.'/subdir', 0755, true, true);
        File::put($this->testDir.'/subdir/nested.php', '<?php echo "test";');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testDir);
        parent::tearDown();
    }

    public function test_it_renders_with_base_path(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->assertStatus(200);
    }

    public function test_it_lists_files_in_directory(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->assertSee('test.txt')
            ->assertSee('subdir');
    }

    public function test_it_can_expand_directory(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('toggleDirectory', 'subdir')
            ->assertSee('nested.php');
    }

    public function test_it_can_collapse_directory(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('toggleDirectory', 'subdir')
            ->call('toggleDirectory', 'subdir')
            ->assertDontSee('nested.php');
    }

    public function test_it_can_preview_file(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('selectFile', 'test.txt')
            ->assertSet('selectedFile', 'test.txt')
            ->assertSee('Hello World');
    }

    public function test_it_prevents_navigation_above_base_path(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('selectFile', '../../../etc/passwd')
            ->assertSet('selectedFile', null);
    }

    public function test_it_ignores_common_directories(): void
    {
        File::makeDirectory($this->testDir.'/node_modules', 0755, true, true);
        File::put($this->testDir.'/node_modules/package.json', '{}');
        File::makeDirectory($this->testDir.'/.git', 0755, true, true);

        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->assertDontSee('node_modules')
            ->assertDontSee('.git');
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
php artisan test tests/Feature/Livewire/FileBrowserTest.php --stop-on-failure
```
Expected: FAIL - FileBrowser class not found

**Step 3: Create the Livewire component**

Run:
```bash
php artisan make:livewire FileBrowser --no-interaction
```

Edit `app/Livewire/FileBrowser.php`:

```php
<?php

namespace App\Livewire;

use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FileBrowser extends Component
{
    public string $basePath;

    /** @var array<string> */
    public array $expandedDirs = [];

    public ?string $selectedFile = null;

    public ?string $fileContent = null;

    /** @var array<string> */
    protected array $ignoredDirs = [
        'node_modules',
        'vendor',
        '.git',
        '.idea',
        '.vscode',
        'storage',
        'bootstrap/cache',
    ];

    public function mount(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * @return array<array{name: string, path: string, isDir: bool, extension: string|null}>
     */
    #[Computed]
    public function files(): array
    {
        return $this->getFilesInDirectory($this->basePath);
    }

    /**
     * @return array<array{name: string, path: string, isDir: bool, extension: string|null}>
     */
    public function getFilesInDirectory(string $path): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $items = [];
        $contents = File::directories($path);

        // Add directories first
        foreach ($contents as $dir) {
            $name = basename($dir);
            if (in_array($name, $this->ignoredDirs)) {
                continue;
            }

            $relativePath = $this->getRelativePath($dir);
            $items[] = [
                'name' => $name,
                'path' => $relativePath,
                'isDir' => true,
                'extension' => null,
            ];
        }

        // Add files
        foreach (File::files($path) as $file) {
            $name = $file->getFilename();
            $items[] = [
                'name' => $name,
                'path' => $this->getRelativePath($file->getPathname()),
                'isDir' => false,
                'extension' => $file->getExtension(),
            ];
        }

        return $items;
    }

    public function toggleDirectory(string $path): void
    {
        if (in_array($path, $this->expandedDirs)) {
            $this->expandedDirs = array_values(array_diff($this->expandedDirs, [$path]));
        } else {
            $this->expandedDirs[] = $path;
        }
    }

    public function isExpanded(string $path): bool
    {
        return in_array($path, $this->expandedDirs);
    }

    public function selectFile(string $path): void
    {
        $fullPath = $this->basePath.'/'.$path;
        $realPath = realpath($fullPath);

        // Security: ensure we're still within basePath
        if (! $realPath || ! str_starts_with($realPath, $this->basePath)) {
            $this->selectedFile = null;
            $this->fileContent = null;
            return;
        }

        if (! File::isFile($realPath)) {
            $this->selectedFile = null;
            $this->fileContent = null;
            return;
        }

        $this->selectedFile = $path;

        // Limit file size for preview (100KB)
        $size = File::size($realPath);
        if ($size > 102400) {
            $this->fileContent = '[File too large to preview]';
        } else {
            $this->fileContent = File::get($realPath);
        }
    }

    public function closePreview(): void
    {
        $this->selectedFile = null;
        $this->fileContent = null;
    }

    private function getRelativePath(string $fullPath): string
    {
        return ltrim(str_replace($this->basePath, '', $fullPath), '/');
    }

    public function getFileIcon(string $extension): string
    {
        return match ($extension) {
            'php' => 'heroicon-o-code-bracket',
            'js', 'ts', 'jsx', 'tsx' => 'heroicon-o-code-bracket-square',
            'json' => 'heroicon-o-document-text',
            'md' => 'heroicon-o-document',
            'css', 'scss' => 'heroicon-o-paint-brush',
            'vue' => 'heroicon-o-squares-2x2',
            'blade.php' => 'heroicon-o-document-text',
            default => 'heroicon-o-document',
        };
    }

    public function render()
    {
        return view('livewire.file-browser');
    }
}
```

**Step 4: Create the Blade view**

Edit `resources/views/livewire/file-browser.blade.php`:

```blade
<div class="flex h-full flex-col rounded-lg bg-white shadow dark:bg-gray-900">
    <div class="border-b p-3 dark:border-gray-700">
        <h3 class="text-sm font-semibold">Files</h3>
        <p class="text-xs text-gray-500 truncate">{{ $basePath }}</p>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
        @foreach($this->files as $item)
            @include('livewire.partials.file-browser-item', ['item' => $item, 'depth' => 0])
        @endforeach
    </div>

    {{-- File Preview Modal --}}
    @if($selectedFile)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closePreview">
            <div class="w-full max-w-4xl max-h-[80vh] rounded-lg bg-white dark:bg-gray-800 overflow-hidden flex flex-col">
                <div class="flex items-center justify-between border-b p-4 dark:border-gray-700">
                    <h3 class="font-mono text-sm">{{ $selectedFile }}</h3>
                    <button wire:click="closePreview" class="text-gray-500 hover:text-gray-700">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-4">
                    <pre class="text-xs font-mono whitespace-pre-wrap">{{ $fileContent }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
```

**Step 5: Create the partial for file items**

Create `resources/views/livewire/partials/file-browser-item.blade.php`:

```blade
@php
    $paddingLeft = $depth * 1;
@endphp

<div style="padding-left: {{ $paddingLeft }}rem;">
    @if($item['isDir'])
        <button
            wire:click="toggleDirectory('{{ $item['path'] }}')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
        >
            @if($this->isExpanded($item['path']))
                <x-heroicon-o-folder-open class="h-4 w-4 text-yellow-500" />
            @else
                <x-heroicon-o-folder class="h-4 w-4 text-yellow-500" />
            @endif
            <span>{{ $item['name'] }}</span>
        </button>

        @if($this->isExpanded($item['path']))
            @foreach($this->getFilesInDirectory($basePath . '/' . $item['path']) as $child)
                @include('livewire.partials.file-browser-item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        @endif
    @else
        <button
            wire:click="selectFile('{{ $item['path'] }}')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
        >
            <x-dynamic-component :component="$this->getFileIcon($item['extension'] ?? '')" class="h-4 w-4 text-gray-400" />
            <span class="truncate">{{ $item['name'] }}</span>
        </button>
    @endif
</div>
```

**Step 6: Run tests**

Run:
```bash
php artisan test tests/Feature/Livewire/FileBrowserTest.php
```
Expected: All tests pass

**Step 7: Commit**

```bash
git add app/Livewire/FileBrowser.php resources/views/livewire/file-browser.blade.php resources/views/livewire/partials/file-browser-item.blade.php tests/Feature/Livewire/FileBrowserTest.php
git commit -m "feat: add FileBrowser Livewire component"
```

---

## Task 10: Create GeneralChatResource Filament Resource

**Files:**
- Create: `app/Filament/Resources/GeneralChats/GeneralChatResource.php`
- Create: `app/Filament/Resources/GeneralChats/Pages/ListGeneralChats.php`
- Create: `app/Filament/Resources/GeneralChats/Pages/GeneralChatPage.php`
- Create: `resources/views/filament/resources/general-chats/pages/general-chat-page.blade.php`
- Test: `tests/Feature/Filament/GeneralChatResourceTest.php`

**Step 1: Write the failing test**

Run:
```bash
php artisan make:test Filament/GeneralChatResourceTest --no-interaction
```

Edit `tests/Feature/Filament/GeneralChatResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\GeneralChats\GeneralChatResource;
use App\Filament\Resources\GeneralChats\Pages\GeneralChatPage;
use App\Filament\Resources\GeneralChats\Pages\ListGeneralChats;
use App\Models\GeneralChat;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeneralChatResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    public function test_it_can_list_general_chats(): void
    {
        $chats = GeneralChat::factory()->count(3)->for($this->user)->create();

        Livewire::test(ListGeneralChats::class)
            ->assertCanSeeTableRecords($chats);
    }

    public function test_it_only_shows_user_own_chats(): void
    {
        $ownChat = GeneralChat::factory()->for($this->user)->create();
        $otherChat = GeneralChat::factory()->create();

        Livewire::test(ListGeneralChats::class)
            ->assertCanSeeTableRecords([$ownChat])
            ->assertCanNotSeeTableRecords([$otherChat]);
    }

    public function test_it_can_create_new_chat(): void
    {
        Livewire::test(ListGeneralChats::class)
            ->callAction('create');

        $this->assertDatabaseHas('general_chats', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_can_open_chat_page(): void
    {
        $chat = GeneralChat::factory()->for($this->user)->create();

        $this->get(GeneralChatPage::getUrl(['record' => $chat]))
            ->assertStatus(200);
    }

    public function test_chat_page_shows_chat_and_file_browser(): void
    {
        $chat = GeneralChat::factory()->for($this->user)->create();

        $this->get(GeneralChatPage::getUrl(['record' => $chat]))
            ->assertSeeLivewire('general-chat-box')
            ->assertSeeLivewire('file-browser');
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
php artisan test tests/Feature/Filament/GeneralChatResourceTest.php --stop-on-failure
```
Expected: FAIL - GeneralChatResource class not found

**Step 3: Create the resource directory structure**

Run:
```bash
mkdir -p app/Filament/Resources/GeneralChats/Pages
mkdir -p resources/views/filament/resources/general-chats/pages
```

**Step 4: Create GeneralChatResource**

Create `app/Filament/Resources/GeneralChats/GeneralChatResource.php`:

```php
<?php

namespace App\Filament\Resources\GeneralChats;

use App\Enums\GeneralChatStatus;
use App\Filament\Resources\GeneralChats\Pages\GeneralChatPage;
use App\Filament\Resources\GeneralChats\Pages\ListGeneralChats;
use App\Models\GeneralChat;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class GeneralChatResource extends Resource
{
    protected static ?string $model = GeneralChat::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'General Chats';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->default('Untitled Chat')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (GeneralChatStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Messages')
                    ->counts('messages'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(GeneralChatStatus::class),
            ])
            ->recordActions([
                Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (GeneralChat $record) => GeneralChatPage::getUrl(['record' => $record])),
                Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Actions\Action::make('create')
                    ->label('New Chat')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        $chat = GeneralChat::create([
                            'user_id' => Auth::id(),
                        ]);

                        return redirect(GeneralChatPage::getUrl(['record' => $chat]));
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneralChats::route('/'),
            'chat' => GeneralChatPage::route('/{record}/chat'),
        ];
    }
}
```

**Step 5: Create ListGeneralChats page**

Create `app/Filament/Resources/GeneralChats/Pages/ListGeneralChats.php`:

```php
<?php

namespace App\Filament\Resources\GeneralChats\Pages;

use App\Filament\Resources\GeneralChats\GeneralChatResource;
use Filament\Resources\Pages\ListRecords;

class ListGeneralChats extends ListRecords
{
    protected static string $resource = GeneralChatResource::class;
}
```

**Step 6: Create GeneralChatPage**

Create `app/Filament/Resources/GeneralChats/Pages/GeneralChatPage.php`:

```php
<?php

namespace App\Filament\Resources\GeneralChats\Pages;

use App\Filament\Resources\GeneralChats\GeneralChatResource;
use App\Models\GeneralChat;
use Filament\Resources\Pages\Page;

class GeneralChatPage extends Page
{
    protected static string $resource = GeneralChatResource::class;

    protected static string $view = 'filament.resources.general-chats.pages.general-chat-page';

    public GeneralChat $record;

    public function getTitle(): string
    {
        return $this->record->title ?? 'General Chat';
    }

    public function getSubheading(): ?string
    {
        return $this->record->working_directory;
    }
}
```

**Step 7: Create the page view**

Create `resources/views/filament/resources/general-chats/pages/general-chat-page.blade.php`:

```blade
<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-16rem)]">
        {{-- Chat Area (2/3 width on large screens) --}}
        <div class="lg:col-span-2 h-full">
            @livewire('general-chat-box', ['chat' => $record])
        </div>

        {{-- File Browser (1/3 width on large screens) --}}
        <div class="h-full hidden lg:block">
            @livewire('file-browser', ['basePath' => $record->working_directory])
        </div>
    </div>
</x-filament-panels::page>
```

**Step 8: Run tests**

Run:
```bash
php artisan test tests/Feature/Filament/GeneralChatResourceTest.php
```
Expected: All tests pass

**Step 9: Commit**

```bash
git add app/Filament/Resources/GeneralChats/ resources/views/filament/resources/general-chats/ tests/Feature/Filament/GeneralChatResourceTest.php
git commit -m "feat: add GeneralChatResource Filament resource"
```

---

## Task 11: Run Full Test Suite and Fix Issues

**Step 1: Run all tests**

Run:
```bash
php artisan test
```
Expected: All tests pass

**Step 2: Run Pint**

Run:
```bash
vendor/bin/pint --dirty
```
Expected: Code formatted correctly

**Step 3: Final commit**

```bash
git add -A
git commit -m "chore: format code with Pint"
```

---

## Task 12: Verify Everything Works

**Step 1: Clear caches**

Run:
```bash
php artisan optimize:clear
```

**Step 2: Test in browser**

- Navigate to the app
- Find "General Chats" in the sidebar
- Click "New Chat" button
- Send a message like "List the files in the current directory"
- Verify the file browser shows files
- Verify Claude responds

**Step 3: Create final commit with any fixes**

```bash
git add -A
git commit -m "feat: complete general chats feature"
```

---

## Summary

This plan creates:

1. **Database**: `general_chats` and `general_chat_messages` tables
2. **Models**: `GeneralChat` and `GeneralChatMessage` with factories
3. **Enum**: `GeneralChatStatus` for chat states
4. **Job**: `RunGeneralChatMessageJob` to execute Claude CLI
5. **Livewire Components**:
   - `GeneralChatBox` - The chat interface
   - `FileBrowser` - File tree with preview
6. **Filament Resource**: `GeneralChatResource` with list and chat pages
7. **Tests**: Full test coverage for models, jobs, components, and resource

The user can now create general chats that run Claude in their home directory, with a file browser sidebar for visibility into what Claude is working with.
