# Major Upgrade Automation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Automatically create and run a “major upgrade” task for Dependabot major updates, producing a PR and a read-only review site on the production server.

**Architecture:** Add a `MajorUpgradeRun` model and status enum, trigger creation in `SecurityManagementService`, and implement a `MajorUpgradeService` that drives orchestration prompts and Ploi review-site provisioning (read-only DB user + env overrides). Expose runs in Filament.

**Tech Stack:** Laravel 11, Filament v4, Ploi CLI + API, GitHub CLI (`gh`).

---

### Task 1: Add MajorUpgradeRun model + migration + enum

**Files:**
- Create: `app/Enums/MajorUpgradeStatus.php`
- Create: `app/Models/MajorUpgradeRun.php`
- Create: `database/migrations/2026_01_24_120000_create_major_upgrade_runs_table.php`
- Modify: `app/Models/Repository.php`
- Test: `tests/Feature/MajorUpgradeRunTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Enums\MajorUpgradeStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Repository;

it('creates a major upgrade run with default status', function () {
    $repo = Repository::factory()->create();
    $run = MajorUpgradeRun::create([
        'repository_id' => $repo->id,
        'github_pr_number' => 123,
        'status' => MajorUpgradeStatus::Pending,
    ]);

    expect($run->status)->toBe(MajorUpgradeStatus::Pending);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MajorUpgradeRunTest`
Expected: FAIL (class/table missing)

**Step 3: Write minimal implementation**

```php
// app/Enums/MajorUpgradeStatus.php
namespace App\Enums;

enum MajorUpgradeStatus: string
{
    case Pending = 'pending';
    case Researching = 'researching';
    case Upgrading = 'upgrading';
    case Fixing = 'fixing';
    case Testing = 'testing';
    case PrOpened = 'pr_opened';
    case ReviewSiteCreated = 'review_site_created';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

```php
// app/Models/MajorUpgradeRun.php
namespace App\Models;

use App\Enums\MajorUpgradeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MajorUpgradeRun extends Model
{
    protected $fillable = [
        'repository_id',
        'github_pr_number',
        'status',
        'source_pr_url',
        'source_pr_sha',
        'work_branch',
        'upgrade_summary',
        'error_message',
        'review_site_url',
        'review_site_id',
        'created_by_task_id',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MajorUpgradeStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
```

```php
// database/migrations/2026_01_24_120000_create_major_upgrade_runs_table.php
Schema::create('major_upgrade_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('github_pr_number');
    $table->string('status');
    $table->string('source_pr_url')->nullable();
    $table->string('source_pr_sha')->nullable();
    $table->string('work_branch')->nullable();
    $table->text('upgrade_summary')->nullable();
    $table->text('error_message')->nullable();
    $table->string('review_site_url')->nullable();
    $table->string('review_site_id')->nullable();
    $table->foreignId('created_by_task_id')->nullable()->constrained('tasks')->nullOnDelete();
    $table->timestamp('last_checked_at')->nullable();
    $table->timestamps();

    $table->index(['repository_id', 'github_pr_number']);
    $table->index('status');
});
```

```php
// app/Models/Repository.php
public function majorUpgradeRuns()
{
    return $this->hasMany(\App\Models\MajorUpgradeRun::class);
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MajorUpgradeRunTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Enums/MajorUpgradeStatus.php app/Models/MajorUpgradeRun.php app/Models/Repository.php database/migrations/2026_01_24_120000_create_major_upgrade_runs_table.php tests/Feature/MajorUpgradeRunTest.php
git commit -m "Add major upgrade run model"
```

---

### Task 2: Trigger major-upgrade run + task creation in SecurityManagementService

**Files:**
- Modify: `app/Services/SecurityManagementService.php`
- Modify: `app/Models/Repository.php`
- Test: `tests/Feature/SecurityManagementMajorUpgradeTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Enums\MajorUpgradeStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Repository;
use App\Models\Task;
use App\Services\SecurityManagementService;
use Illuminate\Support\Facades\Http;

it('creates a major upgrade run and task for major updates', function () {
    $repo = Repository::factory()->create(['security_management_enabled' => true]);

    $service = app(SecurityManagementService::class);
    // simulate major update by calling new helper (to implement)
    $service->createMajorUpgradeRunForTest($repo, 101);

    $run = MajorUpgradeRun::where('repository_id', $repo->id)->first();

    expect($run)->not->toBeNull();
    expect($run->status)->toBe(MajorUpgradeStatus::Pending);
    expect(Task::where('id', $run->created_by_task_id)->exists())->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityManagementMajorUpgradeTest`
Expected: FAIL (helper/method missing)

**Step 3: Write minimal implementation**

```php
// app/Services/SecurityManagementService.php (add helper + call on major)
private function createMajorUpgradeRun(Repository $repo, int $prNumber, array $prPayload): MajorUpgradeRun
{
    $task = Task::create([
        'title' => "Major Upgrade: {$repo->name} PR #{$prNumber}",
        'repository_id' => $repo->id,
        'ai_provider_id' => $this->aiResolver->orchestratorProvider()?->id,
        'status' => \App\Enums\TaskStatus::Pending,
    ]);

    return MajorUpgradeRun::create([
        'repository_id' => $repo->id,
        'github_pr_number' => $prNumber,
        'status' => MajorUpgradeStatus::Pending,
        'source_pr_url' => $prPayload['html_url'] ?? null,
        'source_pr_sha' => $prPayload['head']['sha'] ?? null,
        'created_by_task_id' => $task->id,
    ]);
}
```

And call `createMajorUpgradeRun` when `update_type === 'major'` (before setting `NeedsUserAction`).

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityManagementMajorUpgradeTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/SecurityManagementService.php tests/Feature/SecurityManagementMajorUpgradeTest.php
git commit -m "Create major upgrade run from security management"
```

---

### Task 3: MajorUpgradeService orchestration + prompt

**Files:**
- Create: `app/Services/MajorUpgradeService.php`
- Create: `resources/prompts/major-upgrade/orchestrator.md`
- Modify: `app/Jobs/RunSecurityManagementJob.php`
- Test: `tests/Feature/MajorUpgradeServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Models\MajorUpgradeRun;
use App\Models\Repository;
use App\Models\Task;
use App\Services\MajorUpgradeService;

it('dispatches orchestrator prompt for major upgrade run', function () {
    $repo = Repository::factory()->create();
    $task = Task::factory()->create(['repository_id' => $repo->id]);

    $run = MajorUpgradeRun::create([
        'repository_id' => $repo->id,
        'github_pr_number' => 50,
        'status' => \App\Enums\MajorUpgradeStatus::Pending,
        'created_by_task_id' => $task->id,
    ]);

    $service = app(MajorUpgradeService::class);
    $service->dispatchOrchestratorPrompt($run);

    $this->assertDatabaseHas('messages', [
        'task_id' => $task->id,
        'role' => 'user',
    ]);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MajorUpgradeServiceTest`
Expected: FAIL (service missing)

**Step 3: Write minimal implementation**

```php
// app/Services/MajorUpgradeService.php
namespace App\Services;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\MajorUpgradeStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Support\Facades\File;

class MajorUpgradeService
{
    public function dispatchOrchestratorPrompt(MajorUpgradeRun $run): void
    {
        $task = Task::findOrFail($run->created_by_task_id);
        $promptPath = resource_path('prompts/major-upgrade/orchestrator.md');
        $template = File::exists($promptPath) ? File::get($promptPath) : 'You are the major upgrade orchestrator.';

        $payload = [
            'repo' => $run->repository->full_name,
            'pr_number' => $run->github_pr_number,
            'head_sha' => $run->source_pr_sha,
        ];

        $content = rtrim($template)."\n\n```json\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n```";

        $message = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $content,
        ]);

        $task->dispatchMessage($message);
        $run->update(['status' => MajorUpgradeStatus::Researching]);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MajorUpgradeServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/MajorUpgradeService.php resources/prompts/major-upgrade/orchestrator.md tests/Feature/MajorUpgradeServiceTest.php
git commit -m "Add major upgrade orchestrator service"
```

---

### Task 4: Ploi review-site provisioning + read-only DB user

**Files:**
- Modify: `app/Services/PloiService.php`
- Modify: `app/Services/MajorUpgradeService.php`
- Test: `tests/Feature/PloiReadonlyUserTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Services\PloiService;
use Illuminate\Support\Facades\Process;

it('creates a readonly db user via ploi cli', function () {
    Process::fake([
        '*' => Process::result('', 0, ''),
    ]);

    $ploi = new PloiService;
    $result = $ploi->createReadonlyDbUser('1', 'db_name', 'ro_user', 'secret');

    expect($result)->toBeTrue();
    Process::assertRan(fn ($process) => str_contains($process->command(), 'database:create-user')
        && str_contains($process->command(), '--readonly'));
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PloiReadonlyUserTest`
Expected: FAIL (method missing)

**Step 3: Write minimal implementation**

```php
// app/Services/PloiService.php
public function createReadonlyDbUser(string $serverId, string $database, string $user, string $password): bool
{
    $result = Process::run([
        'ploi', 'database:create-user',
        '--server='.$serverId,
        '--database='.$database,
        '--user='.$user,
        '--password='.$password,
        '--readonly',
        '--no-interaction',
    ]);

    return $result->successful();
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PloiReadonlyUserTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/PloiService.php tests/Feature/PloiReadonlyUserTest.php
git commit -m "Add Ploi readonly DB user helper"
```

---

### Task 5: Review env generation + site creation

**Files:**
- Modify: `app/Services/PloiService.php`
- Modify: `app/Services/MajorUpgradeService.php`
- Test: `tests/Feature/ReviewEnvBuildTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Services\PloiService;

it('replaces app url and db credentials in review env', function () {
    $env = "APP_URL=https://prod.example.com\nDB_USERNAME=prod\nDB_PASSWORD=prodpass\n";

    $ploi = new PloiService;
    $newEnv = $ploi->buildReviewEnv($env, 'https://review.example.com', 'ro_user', 'ro_pass');

    expect($newEnv)->toContain('APP_URL=https://review.example.com');
    expect($newEnv)->toContain('DB_USERNAME=ro_user');
    expect($newEnv)->toContain('DB_PASSWORD=ro_pass');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReviewEnvBuildTest`
Expected: FAIL (method missing)

**Step 3: Write minimal implementation**

```php
// app/Services/PloiService.php
public function buildReviewEnv(string $env, string $appUrl, string $dbUser, string $dbPass): string
{
    $env = preg_replace('/^APP_URL=.*/m', "APP_URL={$appUrl}", $env) ?? $env;
    $env = preg_replace('/^DB_USERNAME=.*/m', "DB_USERNAME={$dbUser}", $env) ?? $env;
    $env = preg_replace('/^DB_PASSWORD=.*/m', "DB_PASSWORD={$dbPass}", $env) ?? $env;

    if (! str_contains($env, 'APP_URL=')) {
        $env .= "\nAPP_URL={$appUrl}";
    }

    return $env;
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReviewEnvBuildTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/PloiService.php tests/Feature/ReviewEnvBuildTest.php
git commit -m "Build review env for readonly site"
```

---

### Task 6: Filament UI for Major Upgrade runs

**Files:**
- Create: `app/Filament/Resources/MajorUpgradeRunResource.php`
- Create: `app/Filament/Resources/MajorUpgradeRunResource/Pages/ListMajorUpgradeRuns.php`
- Test: `tests/Feature/Filament/MajorUpgradeRunResourceTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Filament\Resources\MajorUpgradeRunResource\Pages\ListMajorUpgradeRuns;
use App\Models\MajorUpgradeRun;
use App\Models\Repository;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows major upgrade runs', function () {
    $repo = Repository::factory()->create(['user_id' => $this->user->id]);
    $run = MajorUpgradeRun::create([
        'repository_id' => $repo->id,
        'github_pr_number' => 1,
        'status' => \App\Enums\MajorUpgradeStatus::Pending,
    ]);

    livewire(ListMajorUpgradeRuns::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$run]);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MajorUpgradeRunResourceTest`
Expected: FAIL (resource missing)

**Step 3: Write minimal implementation**

```php
// app/Filament/Resources/MajorUpgradeRunResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\MajorUpgradeRunResource\Pages;
use App\Models\MajorUpgradeRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MajorUpgradeRunResource extends Resource
{
    protected static ?string $model = MajorUpgradeRun::class;
    protected static ?string $navigationGroup = 'Automation';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('repository.full_name')->label('Repository'),
                Tables\Columns\TextColumn::make('github_pr_number')->label('PR'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('review_site_url')->label('Review Site')->url(fn ($state) => $state, true),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMajorUpgradeRuns::route('/'),
        ];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MajorUpgradeRunResourceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Filament/Resources/MajorUpgradeRunResource.php app/Filament/Resources/MajorUpgradeRunResource/Pages/ListMajorUpgradeRuns.php tests/Feature/Filament/MajorUpgradeRunResourceTest.php
git commit -m "Add major upgrade runs admin view"
```

---

## Notes

- All tests use TDD (red/green).
- Each task should be executed sequentially with frequent commits.
- After implementation, use superpowers:verification-before-completion and run targeted tests.

