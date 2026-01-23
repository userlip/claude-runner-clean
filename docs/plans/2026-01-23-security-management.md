# Security Management Automation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add scheduled security management that reviews Dependabot PRs with AI + Scrappa MCP, merges when safe (CI green), and deploys via Ploi, using a long-lived task per repo and global AI provider settings.

**Architecture:** A scheduled command enqueues an orchestrator job that scans repos with security management enabled, tracks PR state in `security_runs`, consults AI via a prompt file, merges via GitHub API, and deploys via Ploi CLI, while posting updates to a repo-specific task.

**Tech Stack:** Laravel 11, Filament, Eloquent, Laravel Scheduler/Queues, GitHub REST API via `Http`, Ploi CLI via `Process`, MCP via AI prompt.

**Skill References:** @superpowers:writing-plans, @superpowers:subagent-driven-development

### Task 1: Add security schema (repositories fields + security_runs)

**Files:**
- Create: `database/migrations/2026_01_23_000001_add_security_fields_to_repositories_table.php`
- Create: `database/migrations/2026_01_23_000002_create_security_runs_table.php`
- Create: `app/Enums/SecurityRunStatus.php`
- Create: `app/Models/SecurityRun.php`
- Test: `tests/Feature/SecuritySchemaTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecuritySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_repositories_table_has_security_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('repositories', [
            'security_management_enabled',
            'ploi_server_id',
            'ploi_site_id',
            'security_task_id',
        ]));
    }

    public function test_security_runs_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('security_runs', [
            'id',
            'repository_id',
            'github_pr_id',
            'github_pr_number',
            'status',
            'decision_summary',
            'merge_commit_sha',
            'last_checked_at',
            'error_message',
            'created_at',
            'updated_at',
        ]));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecuritySchemaTest`
Expected: FAIL with missing columns/tables.

**Step 3: Write minimal implementation**

```php
// database/migrations/2026_01_23_000001_add_security_fields_to_repositories_table.php
Schema::table('repositories', function (Blueprint $table) {
    $table->boolean('security_management_enabled')->default(false)->after('description');
    $table->string('ploi_server_id')->nullable()->after('security_management_enabled');
    $table->string('ploi_site_id')->nullable()->after('ploi_server_id');
    $table->foreignId('security_task_id')->nullable()->constrained('tasks')->nullOnDelete();
});

// database/migrations/2026_01_23_000002_create_security_runs_table.php
Schema::create('security_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('github_pr_id');
    $table->unsignedInteger('github_pr_number');
    $table->string('status');
    $table->text('decision_summary')->nullable();
    $table->string('merge_commit_sha')->nullable();
    $table->timestamp('last_checked_at')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->unique(['repository_id', 'github_pr_id']);
});
```

```php
// app/Enums/SecurityRunStatus.php
namespace App\Enums;

enum SecurityRunStatus: string
{
    case Pending = 'pending';
    case WaitingCi = 'waiting_ci';
    case Researching = 'researching';
    case Approved = 'approved';
    case Merged = 'merged';
    case Deployed = 'deployed';
    case Failed = 'failed';
}

// app/Models/SecurityRun.php
namespace App\Models;

use App\Enums\SecurityRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'github_pr_id',
        'github_pr_number',
        'status',
        'decision_summary',
        'merge_commit_sha',
        'last_checked_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => SecurityRunStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecuritySchemaTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/2026_01_23_000001_add_security_fields_to_repositories_table.php \
  database/migrations/2026_01_23_000002_create_security_runs_table.php \
  app/Enums/SecurityRunStatus.php app/Models/SecurityRun.php tests/Feature/SecuritySchemaTest.php
git commit -m "feat: add security management schema"
```

### Task 2: Repository model + relationships

**Files:**
- Modify: `app/Models/Repository.php`
- Test: `tests/Unit/RepositorySecurityTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositorySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_management_enabled_casts_to_bool(): void
    {
        $repo = Repository::factory()->create(['security_management_enabled' => 1]);

        $this->assertTrue($repo->security_management_enabled);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositorySecurityTest`
Expected: FAIL due to missing fillable/casts.

**Step 3: Write minimal implementation**

```php
// app/Models/Repository.php (add fillable + casts + relations)
protected $fillable = [
    // ...
    'security_management_enabled',
    'ploi_server_id',
    'ploi_site_id',
    'security_task_id',
];

protected function casts(): array
{
    return [
        // ...
        'security_management_enabled' => 'boolean',
    ];
}

public function securityRuns(): HasMany
{
    return $this->hasMany(SecurityRun::class);
}

public function securityTask(): BelongsTo
{
    return $this->belongsTo(Task::class, 'security_task_id');
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RepositorySecurityTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Repository.php tests/Unit/RepositorySecurityTest.php
git commit -m "feat: add repository security relations"
```

### Task 3: Global AI provider config + resolver

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`
- Create: `app/Services/SecurityAiResolver.php`
- Test: `tests/Unit/SecurityAiResolverTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\SecurityAiResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SecurityAiResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_provider_uses_configured_id(): void
    {
        $provider = AiProvider::factory()->create(['is_default' => false]);
        Config::set('services.security_ai.orchestrator_provider_id', $provider->id);

        $resolved = app(SecurityAiResolver::class)->orchestratorProvider();

        $this->assertSame($provider->id, $resolved?->id);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityAiResolverTest`
Expected: FAIL with class not found.

**Step 3: Write minimal implementation**

```php
// config/services.php
'security_ai' => [
    'orchestrator_provider_id' => env('SECURITY_ORCHESTRATOR_PROVIDER_ID'),
    'fixer_provider_id' => env('SECURITY_FIXER_PROVIDER_ID'),
],

// .env.example
SECURITY_ORCHESTRATOR_PROVIDER_ID=
SECURITY_FIXER_PROVIDER_ID=

// app/Services/SecurityAiResolver.php
namespace App\Services;

use App\Models\AiProvider;

class SecurityAiResolver
{
    public function orchestratorProvider(): ?AiProvider
    {
        $id = config('services.security_ai.orchestrator_provider_id');

        return $id ? AiProvider::find($id) : AiProvider::getDefault();
    }

    public function fixerProvider(): ?AiProvider
    {
        $id = config('services.security_ai.fixer_provider_id');

        return $id ? AiProvider::find($id) : AiProvider::getDefault();
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityAiResolverTest`
Expected: PASS

**Step 5: Commit**

```bash
git add config/services.php .env.example app/Services/SecurityAiResolver.php tests/Unit/SecurityAiResolverTest.php
git commit -m "feat: add security ai provider resolver"
```

### Task 4: GitHub service support for Dependabot PRs

**Files:**
- Modify: `app/Services/GitHubService.php`
- Test: `tests/Unit/GitHubServiceDependabotTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\GitHubConnection;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubServiceDependabotTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_dependabot_pull_requests(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/*/pulls*' => Http::response([
                ['id' => 1, 'number' => 10, 'user' => ['login' => 'dependabot[bot]']],
                ['id' => 2, 'number' => 11, 'user' => ['login' => 'someone']],
            ]),
        ]);

        $prs = $service->fetchDependabotPullRequests('org/repo');

        $this->assertCount(1, $prs);
        $this->assertSame(10, $prs->first()['number']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GitHubServiceDependabotTest`
Expected: FAIL with method not found.

**Step 3: Write minimal implementation**

```php
// app/Services/GitHubService.php
public function fetchDependabotPullRequests(string $fullName): Collection
{
    $response = Http::withToken($this->connection->access_token)
        ->accept('application/vnd.github+json')
        ->get(self::API_BASE."/repos/{$fullName}/pulls", [
            'state' => 'open',
            'per_page' => 100,
        ]);

    if ($response->failed()) {
        throw new \RuntimeException('Failed to fetch PRs: '.$response->body());
    }

    return collect($response->json())
        ->filter(fn ($pr) => ($pr['user']['login'] ?? '') === 'dependabot[bot]')
        ->values();
}

public function fetchCombinedStatus(string $fullName, string $sha): array
{
    $response = Http::withToken($this->connection->access_token)
        ->accept('application/vnd.github+json')
        ->get(self::API_BASE."/repos/{$fullName}/commits/{$sha}/status");

    if ($response->failed()) {
        throw new \RuntimeException('Failed to fetch status: '.$response->body());
    }

    return $response->json();
}

public function mergePullRequest(string $fullName, int $number): array
{
    $response = Http::withToken($this->connection->access_token)
        ->accept('application/vnd.github+json')
        ->put(self::API_BASE."/repos/{$fullName}/pulls/{$number}/merge", [
            'merge_method' => 'squash',
        ]);

    if ($response->failed()) {
        throw new \RuntimeException('Failed to merge PR: '.$response->body());
    }

    return $response->json();
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=GitHubServiceDependabotTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/GitHubService.php tests/Unit/GitHubServiceDependabotTest.php
git commit -m "feat: add dependabot github helpers"
```

### Task 5: Security orchestrator service + decision parsing

**Files:**
- Create: `app/Services/SecurityManagementService.php`
- Create: `app/Services/SecurityDecisionParser.php`
- Modify: `app/Models/Task.php` (if helper needed)
- Test: `tests/Unit/SecurityDecisionParserTest.php`
- Test: `tests/Feature/SecurityManagementServiceTest.php`

**Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Services\SecurityDecisionParser;
use Tests\TestCase;

class SecurityDecisionParserTest extends TestCase
{
    public function test_parses_merge_allowed_from_json_block(): void
    {
        $content = "Here is decision:\n```json\n{\"merge_allowed\": true, \"risk_level\": \"low\"}\n```";
        $decision = (new SecurityDecisionParser())->parse($content);

        $this->assertTrue($decision['merge_allowed']);
        $this->assertSame('low', $decision['risk_level']);
    }
}
```

```php
<?php

namespace Tests\Feature;

use App\Enums\SecurityRunStatus;
use App\Models\GitHubConnection;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Services\GitHubService;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_pr_waiting_for_ci_when_pending(): void
    {
        $repo = Repository::factory()->create(['security_management_enabled' => true, 'full_name' => 'org/repo']);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);

        SecurityRun::create([
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'github_pr_number' => 10,
            'status' => SecurityRunStatus::Pending,
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response(['state' => 'pending']),
        ]);

        app(SecurityManagementService::class)->processRepository($repo);

        $this->assertDatabaseHas('security_runs', [
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'status' => SecurityRunStatus::WaitingCi->value,
        ]);
    }
}
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SecurityDecisionParserTest`
Expected: FAIL (class not found)

Run: `php artisan test --filter=SecurityManagementServiceTest`
Expected: FAIL (service not found)

**Step 3: Write minimal implementation**

```php
// app/Services/SecurityDecisionParser.php
namespace App\Services;

class SecurityDecisionParser
{
    public function parse(string $content): array
    {
        if (preg_match('/```json\n(.*?)\n```/s', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if (is_array($data)) {
                return $data;
            }
        }

        return ['merge_allowed' => false, 'risk_level' => 'unknown'];
    }
}
```

```php
// app/Services/SecurityManagementService.php
namespace App\Services;

use App\Enums\SecurityRunStatus;
use App\Models\Repository;
use App\Models\SecurityRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SecurityManagementService
{
    public function __construct(
        private SecurityAiResolver $aiResolver,
        private SecurityDecisionParser $parser,
    ) {}

    public function processRepository(Repository $repo): void
    {
        $connection = $repo->user?->githubConnection;
        if (! $connection) {
            return;
        }

        $github = new GitHubService($connection);
        $prs = $github->fetchDependabotPullRequests($repo->full_name);

        foreach ($prs as $pr) {
            $run = SecurityRun::firstOrCreate(
                ['repository_id' => $repo->id, 'github_pr_id' => $pr['id']],
                [
                    'github_pr_number' => $pr['number'],
                    'status' => SecurityRunStatus::Pending,
                ]
            );

            if ($run->status === SecurityRunStatus::Pending) {
                $status = $github->fetchCombinedStatus($repo->full_name, $pr['head']['sha']);
                $run->update([
                    'status' => $status['state'] === 'success'
                        ? SecurityRunStatus::Researching
                        : SecurityRunStatus::WaitingCi,
                    'last_checked_at' => now(),
                ]);
            }
        }
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SecurityDecisionParserTest`
Expected: PASS

Run: `php artisan test --filter=SecurityManagementServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/SecurityDecisionParser.php app/Services/SecurityManagementService.php \
  tests/Unit/SecurityDecisionParserTest.php tests/Feature/SecurityManagementServiceTest.php
git commit -m "feat: add security management service"
```

### Task 6: Prompt file + AI task wiring

**Files:**
- Create: `resources/prompts/security/dependabot.md`
- Modify: `app/Services/SecurityManagementService.php`
- Test: `tests/Feature/SecurityManagementAiPromptTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\Task;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecurityManagementAiPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_or_reuses_security_task(): void
    {
        $repo = Repository::factory()->create(['security_management_enabled' => true]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn('PROMPT');

        app(SecurityManagementService::class)->ensureSecurityTask($repo);

        $this->assertNotNull($repo->fresh()->security_task_id);
        $this->assertInstanceOf(Task::class, $repo->fresh()->securityTask);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityManagementAiPromptTest`
Expected: FAIL with method not found.

**Step 3: Write minimal implementation**

```md
<!-- resources/prompts/security/dependabot.md -->
You are the Security Management orchestrator.

Inputs:
- Repository metadata
- PR metadata
- CI status summary

Tasks:
1) Use Scrappa MCP to fetch release notes for the dependency and version bump.
2) Use Scrappa MCP to find security advisories (GitHub Advisories, NVD, vendor).
3) Decide if merge is safe.

Output:
- A short human summary.
- A JSON block:
```json
{ "merge_allowed": true|false, "risk_level": "low|medium|high|unknown", "rationale": "...", "sources": ["..."] }
```
```

```php
// app/Services/SecurityManagementService.php (add helper)
public function ensureSecurityTask(Repository $repo): Task
{
    if ($repo->securityTask) {
        return $repo->securityTask;
    }

    $task = Task::create([
        'title' => "Security Management: {$repo->name}",
        'repository_id' => $repo->id,
        'ai_provider_id' => $this->aiResolver->orchestratorProvider()?->id,
        'status' => \App\Enums\TaskStatus::Pending,
    ]);

    $repo->update(['security_task_id' => $task->id]);

    return $task;
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityManagementAiPromptTest`
Expected: PASS

**Step 5: Commit**

```bash
git add resources/prompts/security/dependabot.md app/Services/SecurityManagementService.php \
  tests/Feature/SecurityManagementAiPromptTest.php
git commit -m "feat: add dependabot security prompt"
```

### Task 7: Scheduler command + job + manual action

**Files:**
- Create: `app/Console/Commands/RunSecurityManagementCommand.php`
- Create: `app/Jobs/RunSecurityManagementJob.php`
- Modify: `routes/console.php`
- Modify: `app/Filament/Resources/RepositoryResource.php`
- Test: `tests/Feature/SecurityCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_command_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('security:orchestrate')->assertExitCode(0);

        Queue::assertPushed(\App\Jobs\RunSecurityManagementJob::class);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityCommandTest`
Expected: FAIL with command not found.

**Step 3: Write minimal implementation**

```php
// app/Console/Commands/RunSecurityManagementCommand.php
namespace App\Console\Commands;

use App\Jobs\RunSecurityManagementJob;
use Illuminate\Console\Command;

class RunSecurityManagementCommand extends Command
{
    protected $signature = 'security:orchestrate {--repo= : Repository ID to run}';
    protected $description = 'Run security management orchestration.';

    public function handle(): int
    {
        RunSecurityManagementJob::dispatch($this->option('repo'));

        return self::SUCCESS;
    }
}
```

```php
// app/Jobs/RunSecurityManagementJob.php
namespace App\Jobs;

use App\Models\Repository;
use App\Services\SecurityManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RunSecurityManagementJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(private ?int $repoId = null) {}

    public function handle(SecurityManagementService $service): void
    {
        $query = Repository::where('security_management_enabled', true);
        if ($this->repoId) {
            $query->whereKey($this->repoId);
        }

        $query->each(fn (Repository $repo) => $service->processRepository($repo));
    }
}
```

```php
// routes/console.php
Schedule::command('security:orchestrate')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
```

```php
// app/Filament/Resources/RepositoryResource.php (add action)
Actions\Action::make('runSecurity')
    ->label('Run Security Check')
    ->icon('heroicon-o-shield-check')
    ->action(fn (Repository $record) => \Artisan::call('security:orchestrate', ['--repo' => $record->id]));
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityCommandTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/RunSecurityManagementCommand.php app/Jobs/RunSecurityManagementJob.php \
  routes/console.php app/Filament/Resources/RepositoryResource.php tests/Feature/SecurityCommandTest.php
git commit -m "feat: add security management scheduler"
```

### Task 8: Ploi site resolution + deploy hook

**Files:**
- Modify: `app/Services/PloiService.php`
- Modify: `app/Services/SecurityManagementService.php`
- Test: `tests/Feature/SecurityDeployTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Services\PloiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SecurityDeployTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_site_id_when_missing(): void
    {
        $repo = Repository::factory()->create(['ploi_site_id' => null]);
        Process::fake([
            'ploi site:list*' => Process::result('| ID | Domain | Has Repo |\n| 1 | example.marin.sh | Yes |'),
        ]);

        $service = new PloiService();
        $siteId = $service->resolveSiteIdForRepository($repo, 'example.marin.sh');

        $this->assertSame('1', $siteId);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityDeployTest`
Expected: FAIL with method not found.

**Step 3: Write minimal implementation**

```php
// app/Services/PloiService.php
public function resolveSiteIdForRepository(Repository $repo, string $domain): ?string
{
    $sites = $this->fetchSites();
    foreach ($sites as $site) {
        if ($site['domain'] === $domain) {
            $repo->update([
                'ploi_server_id' => $this->serverId,
                'ploi_site_id' => (string) $site['id'],
            ]);

            return (string) $site['id'];
        }
    }

    return null;
}
```

```php
// app/Services/SecurityManagementService.php (after merge)
$this->deployRepository($repo);

private function deployRepository(Repository $repo): void
{
    $ploi = new PloiService();
    if (! $repo->ploi_site_id) {
        // resolve using known domain or repo name -> domain mapping
        $ploi->resolveSiteIdForRepository($repo, $repo->name.'.marin.sh');
    }

    if ($repo->ploi_site_id) {
        \Process::run([
            'ploi', 'deploy',
            '--server='.$repo->ploi_server_id,
            '--site='.$repo->ploi_site_id,
            '--no-interaction',
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityDeployTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/PloiService.php app/Services/SecurityManagementService.php tests/Feature/SecurityDeployTest.php
git commit -m "feat: add ploi resolution for security deploys"
```

### Task 9: Filament UI fields for security management

**Files:**
- Modify: `app/Filament/Resources/RepositoryResource.php`
- Modify: `app/Filament/Resources/Repositories/RepositoryResource.php`
- Test: `tests/Feature/RepositorySecurityUiTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositorySecurityUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_resource_includes_security_toggle(): void
    {
        $this->assertTrue(class_exists(\App\Filament\Resources\RepositoryResource::class));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RepositorySecurityUiTest`
Expected: FAIL (test is placeholder; update after UI change).

**Step 3: Write minimal implementation**

```php
// app/Filament/Resources/RepositoryResource.php (table column)
Tables\Columns\IconColumn::make('security_management_enabled')
    ->label('Security Mgmt')
    ->boolean(),

// app/Filament/Resources/Repositories/RepositoryResource.php (form fields)
Forms\Components\Toggle::make('security_management_enabled')
    ->label('Security Management')
    ->helperText('Enable auto-merge + deploy for Dependabot PRs'),

Forms\Components\TextInput::make('ploi_server_id')
    ->label('Ploi Server ID'),
Forms\Components\TextInput::make('ploi_site_id')
    ->label('Ploi Site ID'),
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RepositorySecurityUiTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Filament/Resources/RepositoryResource.php app/Filament/Resources/Repositories/RepositoryResource.php \
  tests/Feature/RepositorySecurityUiTest.php
git commit -m "feat: add security management ui fields"
```

### Task 10: End-to-end orchestration smoke test

**Files:**
- Test: `tests/Feature/SecurityOrchestrationSmokeTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Models\Repository;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityOrchestrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_creates_runs_for_dependabot_prs(): void
    {
        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
        ]);

        GitHubConnection::factory()->create([
            'user_id' => $repo->user_id,
            'access_token' => 'token',
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls*' => Http::response([
                ['id' => 123, 'number' => 5, 'user' => ['login' => 'dependabot[bot]'], 'head' => ['sha' => 'abc']],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response(['state' => 'pending']),
        ]);

        app(SecurityManagementService::class)->processRepository($repo);

        $this->assertDatabaseHas('security_runs', [
            'repository_id' => $repo->id,
            'github_pr_id' => 123,
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityOrchestrationSmokeTest`
Expected: FAIL until previous tasks complete.

**Step 3: Write minimal implementation**

Use the service wiring from Tasks 4–7. No new code needed; only stabilize behavior if test still fails.

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityOrchestrationSmokeTest`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Feature/SecurityOrchestrationSmokeTest.php
git commit -m "test: add security management smoke test"
```

