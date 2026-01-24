# Security Deploy Fix + Merge Overview Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Auto-fix Ploi deploy script when deploy fails due to untracked build artifacts, and add an admin view listing all security merges/deploy outcomes.

**Architecture:** Add a pure helper for injecting a cleanup command into deploy scripts, use Ploi API to update site deploy_script when a specific git error is detected, then retry deploy once. Add a Filament SecurityRun resource to show merge/deploy status across all runs.

**Tech Stack:** Laravel 10, Filament Admin, Process facade, Http client, Ploi API.

### Task 1: Deploy script helper (TDD)

**Files:**
- Create: `app/Support/DeployScriptHelper.php`
- Test: `tests/Unit/DeployScriptHelperTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Support\DeployScriptHelper;

it('inserts cleanup before first git command', function () {
    $script = "cd /var/www\n".
        "git fetch origin && git reset --hard origin/master\n".
        "composer install\n";

    $updated = DeployScriptHelper::ensureCleanup($script, 'rm -rf public/build');

    expect($updated)->toContain("rm -rf public/build\n");
    expect(strpos($updated, 'rm -rf public/build'))
        ->toBeLessThan(strpos($updated, 'git fetch'));
});

it('does not duplicate cleanup if already present', function () {
    $script = "rm -rf public/build\n".
        "git fetch origin\n";

    $updated = DeployScriptHelper::ensureCleanup($script, 'rm -rf public/build');

    expect(substr_count($updated, 'rm -rf public/build'))
        ->toBe(1);
});

it('prepends cleanup if no git command exists', function () {
    $script = "cd /var/www\ncomposer install\n";

    $updated = DeployScriptHelper::ensureCleanup($script, 'rm -rf public/build');

    expect(strpos($updated, 'rm -rf public/build'))
        ->toBe(0);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DeployScriptHelperTest`
Expected: FAIL with class/method not found

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Support;

class DeployScriptHelper
{
    public static function ensureCleanup(string $script, string $cleanupCommand): string
    {
        if (str_contains($script, $cleanupCommand)) {
            return $script;
        }

        $lines = preg_split('/\r\n|\r|\n/', $script) ?: [];
        $insertAt = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/\bgit\b/i', $line)) {
                $insertAt = $index;
                break;
            }
        }

        if ($insertAt === null) {
            array_unshift($lines, $cleanupCommand);
        } else {
            array_splice($lines, $insertAt, 0, [$cleanupCommand]);
        }

        return implode("\n", $lines);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DeployScriptHelperTest`
Expected: PASS

### Task 2: Ploi deploy script update + retry (TDD)

**Files:**
- Modify: `app/Services/PloiService.php`
- Modify: `app/Services/SecurityManagementService.php`
- Modify: `config/services.php`
- Test: `tests/Feature/SecurityDeployScriptFixTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\Task;
use App\Services\SecurityManagementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

it('updates deploy script and retries on untracked merge error', function () {
    Http::fake([
        'https://ploi.io/api/*' => Http::sequence()
            ->push(['data' => ['deploy_script' => "git fetch origin"]], 200)
            ->push(['data' => []], 200),
    ]);

    Process::fake([
        'ploi deploy*' => Process::sequence()
            ->pushProcessResult(exitCode: 1, output: '', errorOutput: 'The following untracked working tree files would be overwritten by merge: public/build/assets/app.js')
            ->pushProcessResult(exitCode: 0, output: 'Deployed', errorOutput: ''),
    ]);

    $repo = Repository::factory()->create([
        'ploi_server_id' => '32593',
        'ploi_site_id' => '95778',
    ]);

    $task = Task::factory()->create(['repository_id' => $repo->id]);
    $run = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 1,
        'github_pr_number' => 1,
        'status' => 'approved',
    ]);

    $service = app(SecurityManagementService::class);
    $service->deployRepositoryForTest($repo); // helper in service for testing

    Process::assertRanTimes('ploi deploy*', 2);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityDeployScriptFixTest`
Expected: FAIL (helper missing, no update/retry)

**Step 3: Implement minimal code**
- Add API URL + token config to `config/services.php`.
- In `PloiService`, add:
  - `getApiToken()` (env token or ~/.ploi/config.php)
  - `fetchSiteDetails()` to read current deploy_script
  - `updateDeployScript()` to patch deploy_script via API
  - `ensureDeployScriptCleanup()` that uses DeployScriptHelper
- In `SecurityManagementService::deployRepository`, capture process result and:
  - detect untracked merge error
  - call `ensureDeployScriptCleanup()`
  - retry deploy once
- Add a test-only wrapper `deployRepositoryForTest()` (or make deployRepository protected and call via subclass)

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityDeployScriptFixTest`
Expected: PASS

### Task 3: Merge/deploy overview (TDD)

**Files:**
- Create: `app/Filament/Resources/SecurityRunResource.php`
- Create: `app/Filament/Resources/SecurityRunResource/Pages/ListSecurityRuns.php`
- Test: `tests/Feature/Filament/SecurityRunResourceTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Filament\Resources\SecurityRunResource\Pages\ListSecurityRuns;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view security runs list', function () {
    $repo = Repository::factory()->create(['user_id' => $this->user->id]);
    $run = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 1,
        'github_pr_number' => 24,
        'status' => 'deployed',
    ]);

    livewire(ListSecurityRuns::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$run]);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SecurityRunResourceTest`
Expected: FAIL (resource missing)

**Step 3: Implement minimal resource**
- Table columns: repository name, PR number (link), status badge, merged icon, deployed icon, merge_commit_sha, updated_at
- Default sort by updated_at desc
- Add status filter

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SecurityRunResourceTest`
Expected: PASS

### Task 4: Full test run

Run: `php artisan test --filter=DeployScriptHelperTest` + `SecurityDeployScriptFixTest` + `SecurityRunResourceTest`
Expected: PASS

### Task 5: Commit

```bash
git add app/Support/DeployScriptHelper.php app/Services/PloiService.php app/Services/SecurityManagementService.php config/services.php app/Filament/Resources/SecurityRunResource.php app/Filament/Resources/SecurityRunResource/Pages/ListSecurityRuns.php tests/Unit/DeployScriptHelperTest.php tests/Feature/SecurityDeployScriptFixTest.php tests/Feature/Filament/SecurityRunResourceTest.php docs/plans/2026-01-24-security-deploy-fix-and-merge-view.md

git commit -m "feat: auto-fix deploy script conflicts and add security run overview"
```
