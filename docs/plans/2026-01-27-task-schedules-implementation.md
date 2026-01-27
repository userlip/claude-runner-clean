# Task Schedules Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add Filament-managed cron schedules that create new repository tasks on a schedule with a prompt, model selection, fresh workspace clones, and optional auto-delete after X minutes.

**Architecture:** Store schedules in `task_schedules`, run a single scheduler command every minute to enqueue due runs (CronExpression, server timezone), and create normal Tasks linked to schedules. Use jobs to clone repos, send prompts, and delete tasks after completion.

**Tech Stack:** Laravel 12, Filament 4, Livewire 3, Cron\CronExpression, Pest

---

### Task 1: Add TaskSchedule model + migration + factory

**Files:**
- Create: `app/Models/TaskSchedule.php`
- Create: `database/migrations/2026_01_27_000000_create_task_schedules_table.php`
- Create: `database/factories/TaskScheduleFactory.php`
- Test: `tests/Feature/Models/TaskScheduleTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\TaskSchedule;
use App\Models\User;

it('creates a task schedule with repository, user, and provider', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['user_id' => $user->id]);
    $provider = AiProvider::factory()->create();

    $schedule = TaskSchedule::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'ai_provider_id' => $provider->id,
        'cron_expression' => '*/5 * * * *',
        'prompt' => 'Run tests',
        'delete_after_minutes' => 30,
    ]);

    expect($schedule->user->is($user))->toBeTrue();
    expect($schedule->repository->is($repository))->toBeTrue();
    expect($schedule->aiProvider->is($provider))->toBeTrue();
    expect($schedule->is_active)->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/TaskScheduleTest.php`
Expected: FAIL (class/migration missing).

**Step 3: Write minimal implementation**

Migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('name');
            $table->text('prompt');
            $table->string('cron_expression');
            $table->json('builder_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('delete_after_minutes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->foreignId('last_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'last_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_schedules');
    }
};
```

Model:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'user_id',
        'ai_provider_id',
        'name',
        'prompt',
        'cron_expression',
        'builder_config',
        'is_active',
        'delete_after_minutes',
        'last_run_at',
        'last_run_status',
        'last_task_id',
    ];

    protected function casts(): array
    {
        return [
            'builder_config' => 'array',
            'is_active' => 'boolean',
            'delete_after_minutes' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function lastTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'last_task_id');
    }
}
```

Factory:
```php
<?php

namespace Database\Factories;

use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\TaskSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskScheduleFactory extends Factory
{
    protected $model = TaskSchedule::class;

    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'user_id' => User::factory(),
            'ai_provider_id' => AiProvider::factory(),
            'name' => $this->faker->sentence(3),
            'prompt' => $this->faker->paragraph(),
            'cron_expression' => '0 * * * *',
            'builder_config' => null,
            'is_active' => true,
            'delete_after_minutes' => null,
        ];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Models/TaskScheduleTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/TaskSchedule.php database/migrations/2026_01_27_000000_create_task_schedules_table.php database/factories/TaskScheduleFactory.php tests/Feature/Models/TaskScheduleTest.php
git commit -m "feat: add task schedules model and table"
```

---

### Task 2: Link tasks to schedules

**Files:**
- Create: `database/migrations/2026_01_27_000001_add_task_schedule_id_to_tasks_table.php`
- Modify: `app/Models/Task.php`
- Test: `tests/Feature/Models/TaskScheduleTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Models/TaskScheduleTest.php`:

```php
it('links tasks back to schedules', function () {
    $schedule = TaskSchedule::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $schedule->repository_id,
        'user_id' => $schedule->user_id,
        'task_schedule_id' => $schedule->id,
    ]);

    expect($task->taskSchedule->is($schedule))->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/TaskScheduleTest.php`
Expected: FAIL (column/relationship missing).

**Step 3: Write minimal implementation**

Migration:
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
            $table->foreignId('task_schedule_id')
                ->nullable()
                ->after('repository_id')
                ->constrained('task_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_schedule_id');
        });
    }
};
```

Task model:
```php
public function taskSchedule(): BelongsTo
{
    return $this->belongsTo(TaskSchedule::class);
}
```

Add `task_schedule_id` to `$fillable`.

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Models/TaskScheduleTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Task.php database/migrations/2026_01_27_000001_add_task_schedule_id_to_tasks_table.php tests/Feature/Models/TaskScheduleTest.php
git commit -m "feat: link tasks to schedules"
```

---

### Task 3: Scheduler command to enqueue due runs

**Files:**
- Create: `app/Console/Commands/RunTaskSchedulesCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Console/RunTaskSchedulesCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Console\Commands\RunTaskSchedulesCommand;
use App\Jobs\RunScheduledTaskJob;
use App\Models\TaskSchedule;
use Illuminate\Support\Facades\Queue;

it('dispatches a job for due schedules', function () {
    Queue::fake();

    $schedule = TaskSchedule::factory()->create([
        'cron_expression' => '* * * * *',
        'last_run_at' => null,
        'is_active' => true,
    ]);

    $this->artisan('tasks:run-schedules')->assertSuccessful();

    Queue::assertPushed(RunScheduledTaskJob::class, fn ($job) => $job->scheduleId === $schedule->id);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Console/RunTaskSchedulesCommandTest.php`
Expected: FAIL (command missing).

**Step 3: Write minimal implementation**

Command:
```php
<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledTaskJob;
use App\Models\TaskSchedule;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunTaskSchedulesCommand extends Command
{
    protected $signature = 'tasks:run-schedules';

    protected $description = 'Dispatch scheduled tasks that are due';

    public function handle(): int
    {
        $now = now();

        TaskSchedule::query()
            ->where('is_active', true)
            ->get()
            ->each(function (TaskSchedule $schedule) use ($now) {
                $expression = new CronExpression($schedule->cron_expression);

                if (! $expression->isDue($now->toDateTimeString())) {
                    return;
                }

                $alreadyRanThisMinute = $schedule->last_run_at
                    && $schedule->last_run_at->format('Y-m-d H:i') === $now->format('Y-m-d H:i');

                if ($alreadyRanThisMinute) {
                    return;
                }

                DB::transaction(function () use ($schedule, $now) {
                    $schedule->refresh();
                    $alreadyRan = $schedule->last_run_at
                        && $schedule->last_run_at->format('Y-m-d H:i') === $now->format('Y-m-d H:i');

                    if ($alreadyRan) {
                        return;
                    }

                    $schedule->update(['last_run_at' => $now]);

                    RunScheduledTaskJob::dispatch($schedule->id);
                });
            });

        return self::SUCCESS;
    }
}
```

Schedule registration in `routes/console.php`:
```php
Schedule::command('tasks:run-schedules')
    ->everyMinute()
    ->runInBackground();
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Console/RunTaskSchedulesCommandTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/RunTaskSchedulesCommand.php routes/console.php tests/Feature/Console/RunTaskSchedulesCommandTest.php
git commit -m "feat: add task schedule runner command"
```

---

### Task 4: Jobs to run schedule, send prompt, and delete after delay

**Files:**
- Create: `app/Jobs/RunScheduledTaskJob.php`
- Create: `app/Jobs/RunScheduledPromptJob.php`
- Create: `app/Jobs/DeleteTaskJob.php`
- Modify: `app/Models/Task.php`
- Test: `tests/Feature/Jobs/RunScheduledTaskJobTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Jobs\RunScheduledTaskJob;
use App\Jobs\CloneRepositoryJob;
use App\Models\TaskSchedule;
use App\Models\Task;
use Illuminate\Support\Facades\Bus;

it('creates a task and chains clone + prompt for schedules', function () {
    Bus::fake();

    $schedule = TaskSchedule::factory()->create([
        'cron_expression' => '* * * * *',
        'prompt' => 'Run tests',
    ]);

    RunScheduledTaskJob::dispatch($schedule->id);

    $task = Task::where('task_schedule_id', $schedule->id)->latest()->first();

    expect($task)->not->toBeNull();
    expect($task->workspace_path)->toContain('/home/ploi/workspaces/');

    Bus::assertChained([
        CloneRepositoryJob::class,
    ]);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Jobs/RunScheduledTaskJobTest.php`
Expected: FAIL (jobs missing).

**Step 3: Write minimal implementation**

`RunScheduledTaskJob`:
```php
<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\TaskSchedule;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;

class RunScheduledTaskJob implements ShouldQueue
{
    use Dispatchable, Queueable, Batchable;

    public function __construct(public int $scheduleId) {}

    public function handle(): void
    {
        $schedule = TaskSchedule::findOrFail($this->scheduleId);

        $workspacePath = '/home/ploi/workspaces/'.Str::slug($schedule->repository->name).'-'.Str::random(8);

        $task = Task::create([
            'user_id' => $schedule->user_id,
            'repository_id' => $schedule->repository_id,
            'task_schedule_id' => $schedule->id,
            'ai_provider_id' => $schedule->ai_provider_id,
            'workspace_path' => $workspacePath,
            'title' => 'Scheduled: '.$schedule->name,
        ]);

        $schedule->update(['last_task_id' => $task->id]);

        Bus::chain([
            new CloneRepositoryJob($task),
            new RunScheduledPromptJob($task->id),
        ])->dispatch();
    }
}
```

`RunScheduledPromptJob`:
```php
<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\Queueable;

class RunScheduledPromptJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $taskId) {}

    public function handle(): void
    {
        $task = Task::findOrFail($this->taskId);
        $schedule = $task->taskSchedule;

        if (! $schedule) {
            return;
        }

        $message = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $schedule->prompt,
        ]);

        $task->dispatchMessage($message, continue: false);
    }
}
```

`DeleteTaskJob`:
```php
<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\Queueable;

class DeleteTaskJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $taskId) {}

    public function handle(): void
    {
        $task = Task::find($this->taskId);

        if ($task) {
            $task->delete();
        }
    }
}
```

Update `Task::markAsCompleted()` and `Task::markAsFailed()` to enqueue delete if schedule has `delete_after_minutes`:
```php
if ($this->taskSchedule && $this->taskSchedule->delete_after_minutes) {
    DeleteTaskJob::dispatch($this->id)->delay(now()->addMinutes($this->taskSchedule->delete_after_minutes));
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Jobs/RunScheduledTaskJobTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Jobs/RunScheduledTaskJob.php app/Jobs/RunScheduledPromptJob.php app/Jobs/DeleteTaskJob.php app/Models/Task.php tests/Feature/Jobs/RunScheduledTaskJobTest.php
git commit -m "feat: add scheduled task execution jobs"
```

---

### Task 5: Filament TaskSchedule resource and UI hooks

**Files:**
- Create: `app/Filament/Resources/TaskSchedules/TaskScheduleResource.php`
- Create: `app/Filament/Resources/TaskSchedules/Pages/ListTaskSchedules.php`
- Create: `app/Filament/Resources/TaskSchedules/Pages/CreateTaskSchedule.php`
- Create: `app/Filament/Resources/TaskSchedules/Pages/EditTaskSchedule.php`
- Modify: `app/Filament/Resources/Tasks/TaskResource.php`
- Modify: `resources/views/livewire/task-chat.blade.php`
- Test: `tests/Feature/Filament/TaskScheduleResourceTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Filament/TaskScheduleResourceTest.php`
Expected: FAIL (resource missing).

**Step 3: Write minimal implementation**

Resource form (core fields):
```php
Forms\Components\TextInput::make('name')->required(),
Forms\Components\Select::make('repository_id')
    ->label('Repository')
    ->options(fn () => Repository::where('user_id', Auth::id())->pluck('full_name', 'id'))
    ->required()
    ->searchable(),
Forms\Components\Select::make('user_id')
    ->label('Owner')
    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
    ->required(),
Forms\Components\Select::make('ai_provider_id')
    ->label('AI Provider')
    ->options(fn () => AiProvider::where('is_active', true)->pluck('display_name', 'id'))
    ->required(),
Forms\Components\Textarea::make('prompt')->required()->rows(6),
Forms\Components\TextInput::make('cron_expression')
    ->label('Cron Expression')
    ->required()
    ->helperText('Server timezone')
    ->rules(['string', 'max:255', fn ($attribute, $value, $fail) => (new \Cron\CronExpression($value)) ? null : $fail('Invalid cron expression')]),
Forms\Components\KeyValue::make('builder_config')->label('Schedule Builder')->nullable(),
Forms\Components\TextInput::make('delete_after_minutes')->numeric()->minValue(1),
Forms\Components\Toggle::make('is_active')->default(true),
```

Table columns: name, repository, owner, cron, next run (computed), last run status, active toggle.

Add a badge to Task chat view near title if `task_schedule_id` exists:
```blade
@if($task->taskSchedule)
    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
        Scheduled: {{ $task->taskSchedule->name }}
    </span>
@endif
```

Add column to Tasks list:
```php
Tables\Columns\TextColumn::make('taskSchedule.name')
    ->label('Schedule')
    ->placeholder('-')
    ->toggleable(),
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Filament/TaskScheduleResourceTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Filament/Resources/TaskSchedules app/Filament/Resources/Tasks/TaskResource.php resources/views/livewire/task-chat.blade.php tests/Feature/Filament/TaskScheduleResourceTest.php
git commit -m "feat: add task schedules filament resource"
```

---

### Task 6: Update schedule status and auto-delete on completion

**Files:**
- Modify: `app/Models/Task.php`
- Test: `tests/Feature/Jobs/RunScheduledTaskJobTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Jobs/RunScheduledTaskJobTest.php`:

```php
use App\Jobs\DeleteTaskJob;
use Illuminate\Support\Facades\Queue;

it('schedules delete after completion when configured', function () {
    Queue::fake();

    $schedule = TaskSchedule::factory()->create(['delete_after_minutes' => 10]);
    $task = Task::factory()->create([
        'task_schedule_id' => $schedule->id,
        'repository_id' => $schedule->repository_id,
        'user_id' => $schedule->user_id,
    ]);

    $task->markAsCompleted();

    Queue::assertPushed(DeleteTaskJob::class);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Jobs/RunScheduledTaskJobTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

Update `Task::markAsCompleted()` and `Task::markAsFailed()`:
```php
if ($this->taskSchedule) {
    $this->taskSchedule->update([
        'last_run_status' => $this->status->value,
        'last_task_id' => $this->id,
    ]);

    if ($this->taskSchedule->delete_after_minutes) {
        DeleteTaskJob::dispatch($this->id)->delay(now()->addMinutes($this->taskSchedule->delete_after_minutes));
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Jobs/RunScheduledTaskJobTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Task.php tests/Feature/Jobs/RunScheduledTaskJobTest.php
git commit -m "feat: update schedule status and auto-delete"
```

---

### Task 7: Formatting and full test run

**Files:**
- Modify: (as needed by Pint)

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty`
Expected: No changes or clean formatting

**Step 2: Run full relevant tests**

Run: `php artisan test tests/Feature/Models/TaskScheduleTest.php tests/Feature/Console/RunTaskSchedulesCommandTest.php tests/Feature/Jobs/RunScheduledTaskJobTest.php tests/Feature/Filament/TaskScheduleResourceTest.php`
Expected: PASS

**Step 3: Commit (if Pint changed files)**

```bash
git add -A
git commit -m "chore: format task schedules"
```

---

## Execution Handoff

Proceed with **superpowers:subagent-driven-development** in this session, executing tasks in order with review after each.
