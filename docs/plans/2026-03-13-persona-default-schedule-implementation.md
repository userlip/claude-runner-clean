# Persona Default Schedule Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ensure every newly created persona gets a linked active task schedule by default, and backfill the missing Convertr persona schedule.

**Architecture:** Create the default schedule as part of persona creation so the existing `tasks:run-schedules` command can continue to be the single dispatch path. Keep the schedule cron configurable, default it to `0 8 * * *` (08:00 UTC), and cover the behavior with a regression test.

**Tech Stack:** Laravel 12, Eloquent observers/models, Pest feature tests, MySQL

### Task 1: Add the regression test

**Files:**
- Modify: `tests/Feature/PersonaCycleAnalysisTest.php`

**Step 1: Write the failing test**

Add a test asserting that creating a persona results in a linked `task_schedules` row with:
- matching `persona_id`
- matching `repository_id` / `user_id` / `ai_provider_id`
- active status
- the default cron expression

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PersonaCycleAnalysisTest.php --filter="creates a default task schedule when a persona is created"`

Expected: FAIL because no schedule is created today.

### Task 2: Implement the minimal fix

**Files:**
- Modify: `app/Observers/PersonaObserver.php`
- Create: `config/personas.php`

**Step 1: Write minimal implementation**

Create the default schedule in the persona `created` observer after storage initialization. Use a config-backed default cron expression and name format based on the persona name. Keep the schedule active and preserve the current scheduler flow.

**Step 2: Run test to verify it passes**

Run: `php artisan test tests/Feature/PersonaCycleAnalysisTest.php --filter="creates a default task schedule when a persona is created"`

Expected: PASS

### Task 3: Verify surrounding behavior

**Files:**
- Reuse existing tests in `tests/Feature/PersonaCycleAnalysisTest.php`

**Step 1: Run related tests**

Run: `php artisan test tests/Feature/PersonaCycleAnalysisTest.php`

Expected: PASS

### Task 4: Backfill production data

**Files:**
- No code file changes; use application context

**Step 1: Insert the missing schedule**

Create the linked task schedule for `Convertr Growth Strategist` with the configured default cron and active status.

**Step 2: Verify backfill**

Query the persona and task schedule tables to confirm the new row exists and is linked correctly.
