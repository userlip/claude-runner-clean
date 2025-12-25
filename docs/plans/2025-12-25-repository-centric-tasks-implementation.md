# Repository-Centric Tasks Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Restructure Claude Runner so tasks belong to repositories directly, with sites as optional deployment targets.

**Architecture:** Tasks work on repositories via per-task workspaces (cloned) or existing sites. Sites can be synced from Ploi and auto-matched to repos. Deploy-to-site flow creates new Ploi sites from workspace work.

**Tech Stack:** Laravel 12, Filament 4, Livewire 3, Ploi CLI, Pest

---

## Phase 1: Database Schema Changes

### Task 1: Modify Sites Table

**Files:**
- Create: `database/migrations/2025_12_26_000001_modify_sites_table_for_sync.php`
- Test: Run migration

**Step 1: Create migration**

Run: `php artisan make:migration modify_sites_table_for_sync --no-interaction`

Edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['repository_id']);
            $table->foreignId('repository_id')->nullable()->change();
            $table->foreign('repository_id')->references('id')->on('repositories')->nullOnDelete();

            $table->string('branch')->nullable()->after('ploi_site_id');
            $table->boolean('synced_from_ploi')->default(false)->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['repository_id']);
            $table->dropColumn(['branch', 'synced_from_ploi']);
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete()->change();
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`

Expected: Migration completes successfully

**Step 3: Commit**

```bash
git add -A
git commit -m "feat: modify sites table for ploi sync support"
```

---

### Task 2: Modify Tasks Table

**Files:**
- Create: `database/migrations/2025_12_26_000002_modify_tasks_table_for_repositories.php`
- Test: Run migration

**Step 1: Create migration**

Run: `php artisan make:migration modify_tasks_table_for_repositories --no-interaction`

Edit the migration:

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
            // Add repository_id
            $table->foreignId('repository_id')->nullable()->after('uuid');
            $table->foreign('repository_id')->references('id')->on('repositories')->cascadeOnDelete();

            // Make site_id nullable
            $table->dropForeign(['site_id']);
            $table->foreignId('site_id')->nullable()->change();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();

            // Add workspace_path
            $table->string('workspace_path')->nullable()->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['repository_id']);
            $table->dropColumn(['repository_id', 'workspace_path']);

            $table->dropForeign(['site_id']);
            $table->foreignId('site_id')->constrained()->cascadeOnDelete()->change();
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`

Expected: Migration completes successfully

**Step 3: Commit**

```bash
git add -A
git commit -m "feat: modify tasks table for repository-centric workflow"
```

---

## Phase 2: Model Updates

### Task 3: Update Site Model

**Files:**
- Modify: `app/Models/Site.php`
- Test: `tests/Feature/Models/SiteTest.php`

**Step 1: Write failing test**

Add to `tests/Feature/Models/SiteTest.php`:

```php
test('site can exist without repository', function () {
    $site = Site::factory()->create(['repository_id' => null]);

    expect($site->repository)->toBeNull();
    expect($site->exists)->toBeTrue();
});

test('site has branch field', function () {
    $site = Site::factory()->create(['branch' => 'feature-auth']);

    expect($site->branch)->toBe('feature-auth');
});

test('site has synced_from_ploi field', function () {
    $site = Site::factory()->create(['synced_from_ploi' => true]);

    expect($site->synced_from_ploi)->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/SiteTest.php`

Expected: Tests fail (fields not in fillable)

**Step 3: Update Site model**

Edit `app/Models/Site.php`:

```php
protected $fillable = [
    'repository_id',
    'domain',
    'path',
    'ploi_site_id',
    'branch',
    'php_version',
    'web_directory',
    'isolated_user',
    'database_name',
    'deploy_script',
    'status',
    'error_message',
    'synced_from_ploi',
];

protected function casts(): array
{
    return [
        'isolated_user' => 'boolean',
        'synced_from_ploi' => 'boolean',
        'status' => SiteStatus::class,
    ];
}
```

**Step 4: Update SiteFactory**

Edit `database/factories/SiteFactory.php` - update definition to allow nullable repository:

```php
public function definition(): array
{
    $subdomain = fake()->unique()->slug(1);

    return [
        'repository_id' => Repository::factory(),
        'domain' => "{$subdomain}.marin.sh",
        'path' => null,
        'ploi_site_id' => null,
        'branch' => null,
        'php_version' => '8.4',
        'web_directory' => '/public',
        'isolated_user' => false,
        'database_name' => null,
        'deploy_script' => null,
        'status' => SiteStatus::Pending,
        'error_message' => null,
        'synced_from_ploi' => false,
    ];
}

public function withoutRepository(): static
{
    return $this->state(['repository_id' => null]);
}

public function syncedFromPloi(): static
{
    return $this->state([
        'synced_from_ploi' => true,
        'status' => SiteStatus::Active,
    ]);
}
```

**Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Models/SiteTest.php`

Expected: All tests pass

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: update Site model for optional repository and sync fields"
```

---

### Task 4: Update Task Model

**Files:**
- Modify: `app/Models/Task.php`
- Modify: `database/factories/TaskFactory.php`
- Test: `tests/Feature/Models/TaskTest.php`

**Step 1: Write failing tests**

Add to `tests/Feature/Models/TaskTest.php`:

```php
test('task belongs to repository', function () {
    $repository = Repository::factory()->create();
    $task = Task::factory()->create(['repository_id' => $repository->id, 'site_id' => null]);

    expect($task->repository->id)->toBe($repository->id);
});

test('task can have workspace_path', function () {
    $task = Task::factory()->create([
        'workspace_path' => '/home/ploi/workspaces/my-repo-abc123',
        'site_id' => null,
    ]);

    expect($task->workspace_path)->toBe('/home/ploi/workspaces/my-repo-abc123');
});

test('task can work on site instead of workspace', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create([
        'repository_id' => $site->repository_id,
        'site_id' => $site->id,
        'workspace_path' => null,
    ]);

    expect($task->site->id)->toBe($site->id);
    expect($task->workspace_path)->toBeNull();
});

test('task working_directory returns workspace_path when set', function () {
    $task = Task::factory()->create([
        'workspace_path' => '/home/ploi/workspaces/test',
        'site_id' => null,
    ]);

    expect($task->working_directory)->toBe('/home/ploi/workspaces/test');
});

test('task working_directory returns site path when on site', function () {
    $site = Site::factory()->active()->create(['path' => '/home/ploi/my-site.marin.sh']);
    $task = Task::factory()->create([
        'repository_id' => $site->repository_id,
        'site_id' => $site->id,
        'workspace_path' => null,
    ]);

    expect($task->working_directory)->toBe('/home/ploi/my-site.marin.sh');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/TaskTest.php`

Expected: Tests fail

**Step 3: Update Task model**

Edit `app/Models/Task.php`:

```php
<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'repository_id',
        'site_id',
        'workspace_path',
        'session_id',
        'status',
        'max_turns',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'max_turns' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->uuid ??= Str::uuid();
            $task->session_id ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getWorkingDirectoryAttribute(): ?string
    {
        if ($this->workspace_path) {
            return $this->workspace_path;
        }

        return $this->site?->path;
    }

    public function isRunning(): bool
    {
        return $this->status === TaskStatus::Running;
    }

    public function isInWorkspace(): bool
    {
        return $this->workspace_path !== null;
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => TaskStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}
```

**Step 4: Update TaskFactory**

Edit `database/factories/TaskFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Repository;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'repository_id' => Repository::factory(),
            'site_id' => null,
            'workspace_path' => '/home/ploi/workspaces/'.fake()->slug(1).'-'.Str::random(8),
            'session_id' => Str::uuid(),
            'status' => TaskStatus::Pending,
            'max_turns' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function onSite(Site $site = null): static
    {
        return $this->state(function () use ($site) {
            $site ??= Site::factory()->active()->create();
            return [
                'repository_id' => $site->repository_id,
                'site_id' => $site->id,
                'workspace_path' => null,
            ];
        });
    }

    public function running(): static
    {
        return $this->state([
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => TaskStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => TaskStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    public function withMaxTurns(int $turns): static
    {
        return $this->state(['max_turns' => $turns]);
    }
}
```

**Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Models/TaskTest.php`

Expected: All tests pass

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: update Task model for repository-centric workflow"
```

---

### Task 5: Update Repository Model

**Files:**
- Modify: `app/Models/Repository.php`
- Test: `tests/Feature/Models/RepositoryTest.php`

**Step 1: Write failing test**

Add to `tests/Feature/Models/RepositoryTest.php`:

```php
test('repository has many tasks', function () {
    $repository = Repository::factory()->create();
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    expect($repository->tasks)->toHaveCount(1);
    expect($repository->tasks->first()->id)->toBe($task->id);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/RepositoryTest.php`

Expected: Test fails (method not found)

**Step 3: Add tasks relationship to Repository**

Edit `app/Models/Repository.php`, add:

```php
public function tasks(): HasMany
{
    return $this->hasMany(Task::class);
}
```

And add the import at the top if not present.

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Models/RepositoryTest.php`

Expected: All tests pass

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add tasks relationship to Repository model"
```

---

## Phase 3: Ploi Site Syncing

### Task 6: Create PloiService

**Files:**
- Create: `app/Services/PloiService.php`
- Test: `tests/Feature/Services/PloiServiceTest.php`

**Step 1: Write failing test**

Create `tests/Feature/Services/PloiServiceTest.php`:

```php
<?php

use App\Models\Repository;
use App\Models\Site;
use App\Services\PloiService;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config(['services.ploi.server_id' => '105384']);
});

test('sync sites creates new sites from ploi', function () {
    Process::fake([
        '*site:list*' => Process::result(
            output: '+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| ID     | Server | Domain                 | Project type | Last deploy at      | PHP version | Has repository |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| 335361 | 105384 | test-site.marin.sh     | laravel      | 2025-12-25 10:57:31 | 8.4         | No             |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+',
        ),
    ]);

    $service = new PloiService;
    $count = $service->syncSites();

    expect($count)->toBe(1);
    expect(Site::where('domain', 'test-site.marin.sh')->exists())->toBeTrue();

    $site = Site::where('domain', 'test-site.marin.sh')->first();
    expect($site->synced_from_ploi)->toBeTrue();
    expect($site->ploi_site_id)->toBe('335361');
    expect($site->php_version)->toBe('8.4');
});

test('sync sites updates existing sites', function () {
    $site = Site::factory()->syncedFromPloi()->create([
        'domain' => 'existing.marin.sh',
        'ploi_site_id' => '123456',
        'php_version' => '8.3',
    ]);

    Process::fake([
        '*site:list*' => Process::result(
            output: '+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| ID     | Server | Domain                 | Project type | Last deploy at      | PHP version | Has repository |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| 123456 | 105384 | existing.marin.sh      | laravel      | 2025-12-25 10:57:31 | 8.4         | No             |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+',
        ),
    ]);

    $service = new PloiService;
    $service->syncSites();

    $site->refresh();
    expect($site->php_version)->toBe('8.4');
});

test('sync sites auto-matches repositories', function () {
    $repo = Repository::factory()->create([
        'full_name' => 'userlip/my-project',
    ]);

    Process::fake([
        '*site:list*' => Process::result(
            output: '+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| ID     | Server | Domain                 | Project type | Last deploy at      | PHP version | Has repository |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| 999999 | 105384 | my-project.marin.sh    | laravel      | 2025-12-25 10:57:31 | 8.4         | Yes            |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+',
        ),
        // Mock the repository info call
        '*' => Process::result(output: 'Repository: userlip/my-project'),
    ]);

    $service = new PloiService;
    $service->syncSites();

    $site = Site::where('domain', 'my-project.marin.sh')->first();
    expect($site->repository_id)->toBe($repo->id);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/PloiServiceTest.php`

Expected: Tests fail (class not found)

**Step 3: Create PloiService**

Create `app/Services/PloiService.php`:

```php
<?php

namespace App\Services;

use App\Enums\SiteStatus;
use App\Models\Repository;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PloiService
{
    protected string $serverId;

    public function __construct()
    {
        $this->serverId = config('services.ploi.server_id');
    }

    public function syncSites(): int
    {
        $sites = $this->fetchSites();
        $synced = 0;

        foreach ($sites as $siteData) {
            $site = Site::updateOrCreate(
                ['ploi_site_id' => $siteData['id']],
                [
                    'domain' => $siteData['domain'],
                    'php_version' => $siteData['php_version'],
                    'path' => $this->guessSitePath($siteData['domain']),
                    'status' => SiteStatus::Active,
                    'synced_from_ploi' => true,
                ]
            );

            // Try to match repository if site has one
            if ($siteData['has_repository'] && ! $site->repository_id) {
                $this->tryMatchRepository($site);
            }

            $synced++;
        }

        return $synced;
    }

    /**
     * @return array<int, array{id: string, domain: string, php_version: string, project_type: string, has_repository: bool}>
     */
    protected function fetchSites(): array
    {
        $result = Process::run([
            'ploi', 'site:list',
            '--server=' . $this->serverId,
            '--no-interaction',
        ]);

        if (! $result->successful()) {
            Log::error('Failed to fetch Ploi sites', ['output' => $result->errorOutput()]);
            throw new \RuntimeException('Failed to fetch sites from Ploi: ' . $result->errorOutput());
        }

        return $this->parseTableOutput($result->output());
    }

    /**
     * @return array<int, array{id: string, domain: string, php_version: string, project_type: string, has_repository: bool}>
     */
    protected function parseTableOutput(string $output): array
    {
        $sites = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Skip header, separator, and empty lines
            if (! str_contains($line, '|') || str_contains($line, '---') || str_contains($line, 'ID')) {
                continue;
            }

            $columns = array_map('trim', explode('|', $line));
            $columns = array_values(array_filter($columns));

            if (count($columns) >= 7) {
                $sites[] = [
                    'id' => $columns[0],
                    'domain' => $columns[2],
                    'project_type' => $columns[3],
                    'php_version' => $columns[5],
                    'has_repository' => $columns[6] === 'Yes',
                ];
            }
        }

        return $sites;
    }

    protected function guessSitePath(string $domain): string
    {
        return "/home/ploi/{$domain}";
    }

    protected function tryMatchRepository(Site $site): void
    {
        // Try to match by domain name pattern (e.g., my-project.marin.sh -> my-project)
        $repoName = explode('.', $site->domain)[0];

        $repository = Repository::where('name', 'like', "%{$repoName}%")
            ->orWhere('full_name', 'like', "%{$repoName}%")
            ->first();

        if ($repository) {
            $site->update(['repository_id' => $repository->id]);
            Log::info("Auto-matched site {$site->domain} to repository {$repository->full_name}");
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/PloiServiceTest.php`

Expected: All tests pass

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add PloiService for site syncing"
```

---

### Task 7: Add Sync Sites Action to SiteResource

**Files:**
- Modify: `app/Filament/Resources/SiteResource.php`
- Test: `tests/Feature/Filament/SiteResourceTest.php`

**Step 1: Write failing test**

Add to `tests/Feature/Filament/SiteResourceTest.php`:

```php
test('can sync sites from ploi', function () {
    Process::fake([
        '*site:list*' => Process::result(
            output: '+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| ID     | Server | Domain                 | Project type | Last deploy at      | PHP version | Has repository |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| 111111 | 105384 | synced-site.marin.sh   | laravel      | 2025-12-25 10:57:31 | 8.4         | No             |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+',
        ),
    ]);

    livewire(ListSites::class)
        ->callAction('syncSites')
        ->assertNotified();

    expect(Site::where('domain', 'synced-site.marin.sh')->exists())->toBeTrue();
});
```

Add import at top: `use Illuminate\Support\Facades\Process;`

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Filament/SiteResourceTest.php`

Expected: Test fails (action not found)

**Step 3: Add sync action to ListSites**

Edit `app/Filament/Resources/SiteResource/Pages/ListSites.php`:

```php
<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Services\PloiService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncSites')
                ->label('Sync from Ploi')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    try {
                        $service = new PloiService;
                        $count = $service->syncSites();

                        Notification::make()
                            ->title('Sites Synced')
                            ->body("Successfully synced {$count} site(s) from Ploi.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Filament/SiteResourceTest.php`

Expected: All tests pass

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Sync Sites action to SiteResource"
```

---

## Phase 4: Clone Repository Job

### Task 8: Create CloneRepositoryJob

**Files:**
- Create: `app/Jobs/CloneRepositoryJob.php`
- Test: `tests/Feature/Jobs/CloneRepositoryJobTest.php`

**Step 1: Create job**

Run: `php artisan make:job CloneRepositoryJob --no-interaction`

**Step 2: Write failing test**

Create `tests/Feature/Jobs/CloneRepositoryJobTest.php`:

```php
<?php

use App\Jobs\CloneRepositoryJob;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Support\Facades\Process;

test('job clones repository to workspace path', function () {
    Process::fake();

    $repository = Repository::factory()->create([
        'clone_url' => 'https://github.com/test/repo.git',
    ]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/repo-abc123',
    ]);

    CloneRepositoryJob::dispatchSync($task);

    Process::assertRan(function ($process) {
        return str_contains(implode(' ', $process->command), 'git clone');
    });
});

test('job builds correct clone command', function () {
    Process::fake();

    $repository = Repository::factory()->create([
        'clone_url' => 'https://github.com/test/my-repo.git',
        'default_branch' => 'main',
    ]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/my-repo-xyz',
    ]);

    CloneRepositoryJob::dispatchSync($task);

    Process::assertRan(function ($process) {
        $cmd = implode(' ', $process->command);
        return str_contains($cmd, 'https://github.com/test/my-repo.git')
            && str_contains($cmd, '/home/ploi/workspaces/my-repo-xyz');
    });
});
```

**Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Jobs/CloneRepositoryJobTest.php`

Expected: Tests fail

**Step 4: Implement CloneRepositoryJob**

Edit `app/Jobs/CloneRepositoryJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class CloneRepositoryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public Task $task) {}

    public function handle(): void
    {
        $repository = $this->task->repository;
        $workspacePath = $this->task->workspace_path;

        Log::info("Cloning repository {$repository->full_name}", [
            'task_id' => $this->task->id,
            'workspace' => $workspacePath,
        ]);

        // Ensure parent directory exists
        $parentDir = dirname($workspacePath);
        if (! is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        // Clone the repository
        $result = Process::timeout(300)->run([
            'git', 'clone',
            '--branch', $repository->default_branch ?? 'main',
            '--single-branch',
            $repository->clone_url,
            $workspacePath,
        ]);

        if (! $result->successful()) {
            Log::error("Failed to clone repository", [
                'task_id' => $this->task->id,
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException("Failed to clone repository: " . $result->errorOutput());
        }

        Log::info("Repository cloned successfully", [
            'task_id' => $this->task->id,
            'workspace' => $workspacePath,
        ]);
    }
}
```

**Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Jobs/CloneRepositoryJobTest.php`

Expected: All tests pass

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add CloneRepositoryJob for workspace cloning"
```

---

## Phase 5: Task Creation UI

### Task 9: Create TaskResource

**Files:**
- Create: `app/Filament/Resources/TaskResource.php`
- Create: `app/Filament/Resources/TaskResource/Pages/ListTasks.php`
- Create: `app/Filament/Resources/TaskResource/Pages/CreateTask.php`
- Create: `app/Filament/Resources/TaskResource/Pages/TaskChat.php`
- Test: `tests/Feature/Filament/TaskResourceTest.php`

**Step 1: Create resource with artisan**

Run: `php artisan make:filament-resource Task --view --no-interaction`

**Step 2: Write failing tests**

Create `tests/Feature/Filament/TaskResourceTest.php`:

```php
<?php

use App\Filament\Resources\TaskResource\Pages\CreateTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Jobs\CloneRepositoryJob;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view tasks list', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    livewire(ListTasks::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$task]);
});

test('can create task with new workspace', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);

    livewire(CreateTask::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'work_location' => 'workspace',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = Task::where('repository_id', $repository->id)->first();
    expect($task)->not->toBeNull();
    expect($task->workspace_path)->toContain('/home/ploi/workspaces/');
    expect($task->site_id)->toBeNull();

    Queue::assertPushed(CloneRepositoryJob::class);
});

test('can create task on existing site', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $site = Site::factory()->active()->create(['repository_id' => $repository->id]);

    livewire(CreateTask::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'work_location' => 'site_' . $site->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = Task::where('repository_id', $repository->id)->first();
    expect($task->site_id)->toBe($site->id);
    expect($task->workspace_path)->toBeNull();

    Queue::assertNotPushed(CloneRepositoryJob::class);
});
```

**Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Filament/TaskResourceTest.php`

Expected: Tests fail

**Step 4: Implement TaskResource**

Edit `app/Filament/Resources/TaskResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationGroup = 'Claude';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Create New Task')
                    ->schema([
                        Forms\Components\Select::make('repository_id')
                            ->label('Repository')
                            ->options(fn () => Repository::where('user_id', Auth::id())
                                ->pluck('full_name', 'id'))
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('work_location', 'workspace')),

                        Forms\Components\Radio::make('work_location')
                            ->label('Work Location')
                            ->options(function (callable $get) {
                                $repoId = $get('repository_id');
                                $options = ['workspace' => 'New Workspace (fresh clone)'];

                                if ($repoId) {
                                    $sites = Site::where('repository_id', $repoId)
                                        ->where('status', 'active')
                                        ->get();

                                    foreach ($sites as $site) {
                                        $options['site_' . $site->id] = "Site: {$site->domain}";
                                    }
                                }

                                return $options;
                            })
                            ->default('workspace')
                            ->required()
                            ->visible(fn (callable $get) => $get('repository_id') !== null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state->color()),
                Tables\Columns\TextColumn::make('workspace_path')
                    ->label('Location')
                    ->formatStateUsing(function (Task $record) {
                        if ($record->site) {
                            return $record->site->domain;
                        }
                        return 'Workspace';
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(\App\Enums\TaskStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('chat')
                    ->label('Open Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Task $record) => Pages\TaskChat::getUrl(['record' => $record])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('repository', fn (Builder $query) => $query->where('user_id', Auth::id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'chat' => Pages\TaskChat::route('/{record}/chat'),
        ];
    }
}
```

**Step 5: Implement CreateTask page**

Edit `app/Filament/Resources/TaskResource/Pages/CreateTask.php`:

```php
<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Jobs\CloneRepositoryJob;
use App\Models\Site;
use App\Models\Task;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $workLocation = $data['work_location'] ?? 'workspace';
        unset($data['work_location']);

        if ($workLocation === 'workspace') {
            $repository = \App\Models\Repository::find($data['repository_id']);
            $data['workspace_path'] = '/home/ploi/workspaces/' . Str::slug($repository->name) . '-' . Str::random(8);
            $data['site_id'] = null;
        } else {
            // Extract site ID from 'site_123' format
            $siteId = (int) str_replace('site_', '', $workLocation);
            $data['site_id'] = $siteId;
            $data['workspace_path'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->workspace_path) {
            CloneRepositoryJob::dispatch($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return TaskResource::getUrl('chat', ['record' => $this->record]);
    }
}
```

**Step 6: Implement ListTasks page**

Edit `app/Filament/Resources/TaskResource/Pages/ListTasks.php`:

```php
<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Task'),
        ];
    }
}
```

**Step 7: Create TaskChat page**

Create `app/Filament/Resources/TaskResource/Pages/TaskChat.php`:

```php
<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Resources\Pages\Page;

class TaskChat extends Page
{
    protected static string $resource = TaskResource::class;

    protected static string $view = 'filament.resources.task-resource.pages.task-chat';

    public Task $record;

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        $location = $this->record->site
            ? $this->record->site->domain
            : 'Workspace';

        return "{$this->record->repository->name} - {$location}";
    }
}
```

**Step 8: Create view for TaskChat**

Create `resources/views/filament/resources/task-resource/pages/task-chat.blade.php`:

```blade
<x-filament-panels::page>
    @livewire('task-chat', ['task' => $this->record])
</x-filament-panels::page>
```

**Step 9: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Filament/TaskResourceTest.php`

Expected: All tests pass

**Step 10: Commit**

```bash
git add -A
git commit -m "feat: add TaskResource with create and chat pages"
```

---

### Task 10: Create TaskChat Livewire Component

**Files:**
- Create: `app/Livewire/TaskChat.php`
- Create: `resources/views/livewire/task-chat.blade.php`
- Test: `tests/Feature/Livewire/TaskChatTest.php`

**Step 1: Create Livewire component**

Run: `php artisan make:livewire TaskChat --no-interaction`

**Step 2: Write failing tests**

Create `tests/Feature/Livewire/TaskChatTest.php`:

```php
<?php

use App\Jobs\RunClaudeMessageJob;
use App\Livewire\TaskChat;
use App\Models\Message;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can render task chat component', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertSuccessful()
        ->assertSee('Start a conversation');
});

test('can send a message', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'Hello Claude!')
        ->call('sendMessage');

    expect(Message::where('content', 'Hello Claude!')->exists())->toBeTrue();
    Queue::assertPushed(RunClaudeMessageJob::class);
});

test('shows repository and location in header', function () {
    $repository = Repository::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'my-awesome-repo',
    ]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertSee('my-awesome-repo')
        ->assertSee('Workspace');
});

test('shows delete workspace button for workspace tasks', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertSee('Delete Workspace');
});

test('does not show delete workspace button for site tasks', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $site = \App\Models\Site::factory()->active()->create(['repository_id' => $repository->id]);
    $task = Task::factory()->onSite($site)->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertDontSee('Delete Workspace');
});
```

**Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/TaskChatTest.php`

Expected: Tests fail

**Step 4: Implement TaskChat component**

Edit `app/Livewire/TaskChat.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TaskChat extends Component
{
    public Task $task;

    public string $prompt = '';

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function chatMessages(): Collection
    {
        return $this->task->messages()->oldest()->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->task->isRunning();
    }

    #[Computed]
    public function locationLabel(): string
    {
        if ($this->task->site) {
            return $this->task->site->domain;
        }

        return 'Workspace';
    }

    public function sendMessage(): void
    {
        $this->validate([
            'prompt' => 'required|string|min:1|max:10000',
        ]);

        $isFirstMessage = $this->task->messages()->count() === 0;

        $userMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => $this->prompt,
        ]);

        RunClaudeMessageJob::dispatch(
            $this->task,
            $userMessage,
            continue: ! $isFirstMessage
        );

        $this->prompt = '';
    }

    public function deleteWorkspace(): void
    {
        if (! $this->task->workspace_path) {
            return;
        }

        if (File::isDirectory($this->task->workspace_path)) {
            File::deleteDirectory($this->task->workspace_path);
        }

        $this->task->update(['workspace_path' => null]);

        $this->dispatch('workspace-deleted');
    }

    public function render()
    {
        return view('livewire.task-chat');
    }
}
```

**Step 5: Create view**

Edit `resources/views/livewire/task-chat.blade.php`:

```blade
<div class="flex h-[calc(100vh-12rem)] flex-col">
    {{-- Header --}}
    <div class="mb-4 flex items-center justify-between rounded-lg bg-white p-4 shadow dark:bg-gray-900">
        <div>
            <h2 class="text-lg font-semibold">{{ $task->repository->name }}</h2>
            <p class="text-sm text-gray-500">{{ $this->locationLabel }}</p>
        </div>
        <div class="flex gap-2">
            @if($task->isInWorkspace())
                <button
                    wire:click="deleteWorkspace"
                    wire:confirm="Are you sure you want to delete this workspace? This cannot be undone."
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                >
                    Delete Workspace
                </button>
            @endif
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex flex-1 flex-col rounded-lg bg-white shadow dark:bg-gray-900">
        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" wire:poll.2s="$refresh">
            @forelse($this->chatMessages as $message)
                <div @class([
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
                    <p>Start a conversation with Claude Code</p>
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

**Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Livewire/TaskChatTest.php`

Expected: All tests pass

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add TaskChat Livewire component"
```

---

### Task 11: Update RunClaudeMessageJob for New Task Model

**Files:**
- Modify: `app/Jobs/RunClaudeMessageJob.php`
- Test: `tests/Feature/Jobs/RunClaudeMessageJobTest.php`

**Step 1: Update test**

Update `tests/Feature/Jobs/RunClaudeMessageJobTest.php` to use new Task factory (already using repository-based tasks from factory update).

**Step 2: Update job to use working_directory**

Edit `app/Jobs/RunClaudeMessageJob.php`, change line 38:

```php
// Old:
$workingDir = $this->task->site->path;

// New:
$workingDir = $this->task->working_directory;
```

**Step 3: Run tests**

Run: `php artisan test tests/Feature/Jobs/RunClaudeMessageJobTest.php`

Expected: All tests pass

**Step 4: Commit**

```bash
git add -A
git commit -m "fix: update RunClaudeMessageJob to use working_directory"
```

---

## Phase 6: Deploy to Site Flow

### Task 12: Create DeployToSiteJob

**Files:**
- Create: `app/Jobs/DeployToSiteJob.php`
- Test: `tests/Feature/Jobs/DeployToSiteJobTest.php`

**Step 1: Create job**

Run: `php artisan make:job DeployToSiteJob --no-interaction`

**Step 2: Write failing test**

Create `tests/Feature/Jobs/DeployToSiteJobTest.php`:

```php
<?php

use App\Enums\SiteStatus;
use App\Jobs\DeployToSiteJob;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config(['services.ploi.server_id' => '105384']);
});

test('job creates branch and pushes', function () {
    Process::fake();

    $repository = Repository::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test-repo',
    ]);

    DeployToSiteJob::dispatchSync($task, 'my-feature');

    Process::assertRan(fn ($p) => str_contains(implode(' ', $p->command), 'git checkout -b my-feature'));
    Process::assertRan(fn ($p) => str_contains(implode(' ', $p->command), 'git push'));
});

test('job creates ploi site', function () {
    Process::fake([
        '*' => Process::result(output: 'Success'),
    ]);

    $repository = Repository::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test-repo',
    ]);

    DeployToSiteJob::dispatchSync($task, 'my-feature');

    Process::assertRan(fn ($p) => str_contains(implode(' ', $p->command), 'site:create'));
});

test('job creates site record on success', function () {
    Process::fake([
        '*' => Process::result(output: 'Success'),
    ]);

    $repository = Repository::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test-repo',
    ]);

    DeployToSiteJob::dispatchSync($task, 'my-feature');

    $site = Site::where('domain', 'my-feature.marin.sh')->first();
    expect($site)->not->toBeNull();
    expect($site->repository_id)->toBe($repository->id);
    expect($site->branch)->toBe('my-feature');
    expect($site->status)->toBe(SiteStatus::Active);
});
```

**Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Jobs/DeployToSiteJobTest.php`

Expected: Tests fail

**Step 4: Implement DeployToSiteJob**

Edit `app/Jobs/DeployToSiteJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DeployToSiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public Task $task,
        public string $subdomain,
        public string $phpVersion = '8.4',
        public string $webDirectory = '/public',
        public ?string $databaseName = null,
    ) {}

    public function handle(): void
    {
        $workspacePath = $this->task->workspace_path;
        $branch = $this->subdomain;
        $domain = "{$this->subdomain}.marin.sh";
        $serverId = config('services.ploi.server_id');

        Log::info("Deploying to site: {$domain}", [
            'task_id' => $this->task->id,
            'branch' => $branch,
        ]);

        try {
            // Step 1: Commit any pending changes
            $this->runGitCommand(['git', 'add', '-A'], $workspacePath);
            $this->runGitCommand([
                'git', 'commit', '-m', 'Deploy to site', '--allow-empty',
            ], $workspacePath);

            // Step 2: Create and push branch
            $this->runGitCommand(['git', 'checkout', '-b', $branch], $workspacePath);
            $this->runGitCommand(['git', 'push', '-u', 'origin', $branch], $workspacePath);

            // Step 3: Create Ploi site
            $this->runPloiCommand([
                'site:create',
                '--server=' . $serverId,
                '--domain=' . $domain,
                '--web-directory=' . $this->webDirectory,
                '--project-type=laravel',
                '--no-interaction',
            ]);

            // Step 4: Install repository
            $this->runPloiCommand([
                'repository:install',
                '--server=' . $serverId,
                '--site=' . $domain,
                '--no-interaction',
            ]);

            // Step 5: Create database if specified
            if ($this->databaseName) {
                $this->runPloiCommand([
                    'database:create',
                    '--server=' . $serverId,
                    '--name=' . $this->databaseName,
                    '--no-interaction',
                ]);
            }

            // Step 6: Deploy
            $this->runPloiCommand([
                'deploy',
                '--server=' . $serverId,
                '--site=' . $domain,
                '--no-interaction',
            ]);

            // Step 7: Create Site record
            $site = Site::create([
                'repository_id' => $this->task->repository_id,
                'domain' => $domain,
                'branch' => $branch,
                'path' => "/home/ploi/{$domain}",
                'php_version' => $this->phpVersion,
                'web_directory' => $this->webDirectory,
                'database_name' => $this->databaseName,
                'status' => SiteStatus::Active,
            ]);

            // Update task to point to site
            $this->task->update(['site_id' => $site->id]);

            Log::info("Site deployed successfully: {$domain}", [
                'site_id' => $site->id,
            ]);

        } catch (\Exception $e) {
            Log::error("Deploy failed: {$e->getMessage()}", [
                'task_id' => $this->task->id,
            ]);
            throw $e;
        }
    }

    protected function runGitCommand(array $command, string $cwd): void
    {
        $result = Process::path($cwd)->run($command);

        if (! $result->successful()) {
            throw new \RuntimeException(
                "Git command failed: " . implode(' ', $command) . "\n" . $result->errorOutput()
            );
        }
    }

    protected function runPloiCommand(array $arguments): void
    {
        $command = array_merge(['ploi'], $arguments);
        $result = Process::timeout(300)->run($command);

        if (! $result->successful()) {
            Log::warning("Ploi command output: " . $result->output());
            // Don't fail on Ploi errors, some commands may not be critical
        }
    }
}
```

**Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Jobs/DeployToSiteJobTest.php`

Expected: All tests pass

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add DeployToSiteJob for workspace deployment"
```

---

### Task 13: Add Deploy Modal to TaskChat

**Files:**
- Modify: `app/Livewire/TaskChat.php`
- Modify: `resources/views/livewire/task-chat.blade.php`
- Test: `tests/Feature/Livewire/TaskChatTest.php`

**Step 1: Add test**

Add to `tests/Feature/Livewire/TaskChatTest.php`:

```php
test('can deploy workspace to site', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('deploySubdomain', 'my-feature')
        ->call('deployToSite');

    Queue::assertPushed(\App\Jobs\DeployToSiteJob::class, function ($job) {
        return $job->subdomain === 'my-feature';
    });
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/TaskChatTest.php`

Expected: Test fails

**Step 3: Add deploy functionality to TaskChat**

Add to `app/Livewire/TaskChat.php`:

```php
public bool $showDeployModal = false;
public string $deploySubdomain = '';
public string $deployPhpVersion = '8.4';
public string $deployWebDirectory = '/public';
public ?string $deployDatabaseName = null;
public bool $showAdvancedOptions = false;

public function openDeployModal(): void
{
    $this->showDeployModal = true;
    $this->deploySubdomain = '';
}

public function closeDeployModal(): void
{
    $this->showDeployModal = false;
}

public function deployToSite(): void
{
    $this->validate([
        'deploySubdomain' => 'required|string|min:1|max:63|regex:/^[a-z0-9-]+$/',
    ]);

    \App\Jobs\DeployToSiteJob::dispatch(
        $this->task,
        $this->deploySubdomain,
        $this->deployPhpVersion,
        $this->deployWebDirectory,
        $this->deployDatabaseName,
    );

    $this->showDeployModal = false;

    $this->dispatch('notify', [
        'message' => "Deploying to {$this->deploySubdomain}.marin.sh...",
    ]);
}
```

**Step 4: Update view with deploy button and modal**

Add to the header section in `resources/views/livewire/task-chat.blade.php`:

```blade
@if($task->isInWorkspace())
    <button
        wire:click="openDeployModal"
        class="rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700"
    >
        Deploy to Site
    </button>
@endif

{{-- Deploy Modal --}}
@if($showDeployModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-lg bg-white p-6 dark:bg-gray-800">
        <h3 class="mb-4 text-lg font-semibold">Deploy to Site</h3>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium">Subdomain</label>
                <div class="mt-1 flex">
                    <input
                        type="text"
                        wire:model="deploySubdomain"
                        class="flex-1 rounded-l-lg border px-3 py-2 dark:bg-gray-700"
                        placeholder="my-feature"
                    >
                    <span class="rounded-r-lg border border-l-0 bg-gray-100 px-3 py-2 dark:bg-gray-600">.marin.sh</span>
                </div>
            </div>

            <div class="text-sm text-gray-500">
                <p>Preview:</p>
                <ul class="ml-4 list-disc">
                    <li>Branch: {{ $deploySubdomain ?: 'subdomain' }}</li>
                    <li>PHP: {{ $deployPhpVersion }}</li>
                    <li>Web directory: {{ $deployWebDirectory }}</li>
                </ul>
            </div>

            <button
                type="button"
                wire:click="$toggle('showAdvancedOptions')"
                class="text-sm text-primary-600"
            >
                {{ $showAdvancedOptions ? '▼' : '▶' }} Advanced Options
            </button>

            @if($showAdvancedOptions)
                <div class="space-y-3 border-t pt-3">
                    <div>
                        <label class="block text-sm font-medium">PHP Version</label>
                        <select wire:model="deployPhpVersion" class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-700">
                            <option value="8.4">8.4</option>
                            <option value="8.3">8.3</option>
                            <option value="8.2">8.2</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Web Directory</label>
                        <input type="text" wire:model="deployWebDirectory" class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Database Name (optional)</label>
                        <input type="text" wire:model="deployDatabaseName" class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-700">
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button
                wire:click="closeDeployModal"
                class="rounded-lg border px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700"
            >
                Cancel
            </button>
            <button
                wire:click="deployToSite"
                class="rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700"
            >
                Deploy
            </button>
        </div>
    </div>
</div>
@endif
```

**Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Livewire/TaskChatTest.php`

Expected: All tests pass

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add deploy to site modal in TaskChat"
```

---

## Phase 7: Cleanup and Final Testing

### Task 14: Remove Old SiteChat Components

**Files:**
- Delete: `app/Livewire/SiteChat.php`
- Delete: `resources/views/livewire/site-chat.blade.php`
- Delete: `app/Filament/Resources/SiteResource/Pages/SiteChat.php`
- Delete: `resources/views/filament/resources/site-resource/pages/site-chat.blade.php`
- Modify: `app/Filament/Resources/SiteResource.php`

**Step 1: Remove old files**

```bash
rm app/Livewire/SiteChat.php
rm resources/views/livewire/site-chat.blade.php
rm app/Filament/Resources/SiteResource/Pages/SiteChat.php
rm resources/views/filament/resources/site-resource/pages/site-chat.blade.php
```

**Step 2: Update SiteResource to remove chat page**

Edit `app/Filament/Resources/SiteResource.php`, update `getPages()`:

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListSites::route('/'),
        'create' => Pages\CreateSite::route('/create'),
        'view' => Pages\ViewSite::route('/{record}'),
    ];
}
```

**Step 3: Commit**

```bash
git add -A
git commit -m "chore: remove old site-based chat components"
```

---

### Task 15: Run Full Test Suite

**Step 1: Run all tests**

Run: `php artisan test`

Expected: All tests pass

**Step 2: Fix any failing tests**

Update tests as needed based on model changes.

**Step 3: Run Pint**

Run: `vendor/bin/pint`

**Step 4: Commit any fixes**

```bash
git add -A
git commit -m "fix: update tests for repository-centric model"
```

---

### Task 16: Final Deployment

**Step 1: Push changes**

```bash
git push origin master
```

**Step 2: Deploy**

```bash
ploi deploy --server=claude-runner --site=claude-runner.marin.sh --no-interaction
```

**Step 3: Run migrations on production**

```bash
php artisan migrate --force
```

---

## Summary

This implementation transforms Claude Runner from a site-centric to repository-centric architecture:

1. **Phase 1-2**: Database and model changes
2. **Phase 3**: Ploi site syncing with auto-matching
3. **Phase 4**: Clone repository job for workspaces
4. **Phase 5**: New TaskResource with create flow
5. **Phase 6**: Deploy to site functionality
6. **Phase 7**: Cleanup and testing

Total: 16 tasks, approximately 2-3 hours of implementation time.
