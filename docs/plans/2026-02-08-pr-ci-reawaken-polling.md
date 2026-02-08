# PR CI Reawaken Polling Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** When a task results in a GitHub PR, automatically poll GitHub until CI finishes for the latest head SHA, then post a new "user" message in the task that tells the agent to check CI results and review feedback.

**Architecture:** Store a small PR-monitor JSON blob in `tasks.session_metadata.pr_monitor`. A scheduled command runs every minute to poll PR state, check-run/commit status, and reviews/comments; when CI transitions from pending to finished for a new head SHA, it creates a new user message and dispatches the agent to resume the same session.

**Tech Stack:** Laravel (console commands + scheduler), Eloquent models, `Http::fake()` for tests, `Queue::fake()` for job dispatch assertions.

### Task 1: Add GitHub API Helpers Needed For Polling

**Files:**
- Modify: `app/Services/GitHubService.php`
- Test: `tests/Feature/GitHubServiceTest.php` (or create if missing)

**Step 1: Write failing test(s)**

Create/extend tests that `GitHubService` can:
- fetch pull requests filtered by `head`
- fetch check-runs list for a SHA
- fetch PR reviews and issue comments

Use `Http::fake()` with expectations on request URLs and ensure methods return arrays.

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter GitHubService`
Expected: FAIL (methods not defined).

**Step 3: Implement minimal helpers**

Add methods:
- `fetchPullRequests(string $fullName, array $query): array`
- `fetchCheckRuns(string $fullName, string $sha): array`
- `fetchPullRequestReviews(string $fullName, int $number): array`
- `fetchIssueComments(string $fullName, int $number, ?string $sinceIso8601 = null): array`

Filter ignored check-runs similarly to existing ignore logic.

**Step 4: Run tests**

Run: `php artisan test --filter GitHubService`
Expected: PASS.

### Task 2: Implement Polling Service + Command

**Files:**
- Create: `app/Services/TaskPullRequestPollingService.php`
- Create: `app/Console/Commands/GitHubPollTaskPullRequestsCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/GitHubPollTaskPullRequestsCommandTest.php`

**Step 1: Write failing test**

Test scenario:
- Create `User`, `GitHubConnection`, `Repository`, `Task`.
- Seed `task.session_metadata['pr_monitor'] = ['active'=>true,'repository_full_name'=>'o/r','pr_number'=>123,'last_notified_sha'=>null]`.
- Fake GitHub endpoints:
  - `GET /repos/o/r/pulls/123` returns head sha `abc`, state open.
  - `GET /repos/o/r/commits/abc/status` returns `pending` first.
  - Next poll returns `failure` and check-runs include a failing run.
- Run command twice.
Assert:
- After first run: no new user message.
- After second run: one new user message created with the "CI finished" text and CI summary.
- Assert the task dispatch job was queued (`RunClaudeMessageJob` or `RunCodexMessageJob` depending on provider).

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter GitHubPollTaskPullRequestsCommand`
Expected: FAIL (command/service missing).

**Step 3: Implement minimal service + command**

Service responsibilities:
- Find tasks with active `session_metadata.pr_monitor`.
- For each:
  - Fetch PR, stop monitoring if closed/merged.
  - Compute `head_sha`.
  - Fetch combined status (existing) to determine `pending|success|failure`.
  - If state != pending AND `last_notified_sha != head_sha`:
    - Fetch check-runs to list failures.
    - Fetch reviews/comments since last markers (best effort).
    - Create a new **user** `Message` with content starting with: "The checks in GitHub CI have finished...".
    - If task status is `Running`, mark message `Queued` only.
    - Otherwise mark `Sent` and call `$task->dispatchMessage($message, continue: true)`.
    - Persist `last_notified_sha` and timestamps back to `session_metadata.pr_monitor`.

Command responsibilities:
- Call the service.

Scheduler:
- Add to `routes/console.php`: `Schedule::command('github:poll-task-prs')->everyMinute()->withoutOverlapping()->runInBackground();`

**Step 4: Run tests**

Run: `php artisan test --filter GitHubPollTaskPullRequestsCommand`
Expected: PASS.

### Task 3: PR Detection (Best Effort) On Task Completion

**Files:**
- Create: `app/Services/TaskPullRequestDetectionService.php`
- Modify: `app/Jobs/RunClaudeMessageJob.php`
- Modify: `app/Jobs/RunCodexMessageJob.php`
- Test: `tests/Feature/TaskPullRequestDetectionServiceTest.php`

**Step 1: Write failing test**

Test that when an assistant message contains a PR URL like `https://github.com/o/r/pull/123`, the detection service stores `session_metadata.pr_monitor` with that PR.

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter TaskPullRequestDetectionService`
Expected: FAIL.

**Step 3: Implement minimal detection**

Detection order:
1. Regex scan recent messages for `github.com/<full_name>/pull/<number>`.
2. If not found, attempt git-based detection (optional, best effort) only if repository + connection exist.

Hook it:
- After marking task completed/failed in runner jobs, call detection service once.

**Step 4: Run tests**

Run: `php artisan test --filter TaskPullRequestDetectionService`
Expected: PASS.

### Task 4: Documentation + Config Guardrails

**Files:**
- Modify: `config/services.php` (optional toggle)
- Modify: `docs/` as needed

**Step 1: Add env toggle**

Add `GITHUB_PR_POLLING_ENABLED=true` (default true) to gate the scheduler/command.

**Step 2: Run full test suite**

Run: `php artisan test`
Expected: PASS.
