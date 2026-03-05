<?php

namespace App\Services;

use App\DataObjects\RalphState;
use App\Models\Task;
use Illuminate\Support\Facades\File;

class RalphWorkspaceService
{
    public function initialize(Task $task, array $config): void
    {
        $ralphPath = $this->getRalphPath($task);

        // Create directory
        File::ensureDirectoryExists($ralphPath);

        // Write prompt.md
        $this->writePrompt($ralphPath, $config);

        // Write prd.json
        $this->writePrd($ralphPath, $config['stories'] ?? []);

        // Write progress.txt
        $this->writeProgress($ralphPath);

        // Create empty guardrails.md
        File::put($ralphPath.'/guardrails.md', $this->guardrailsTemplate());

        // Create empty activity.log
        File::put($ralphPath.'/activity.log', '');

        // Update task with anchor path
        $task->update(['ralph_anchor_path' => $ralphPath.'/prompt.md']);
    }

    public function readState(Task $task): RalphState
    {
        $ralphPath = $this->getRalphPath($task);

        try {
            $prompt = File::get($ralphPath.'/prompt.md');
        } catch (\Exception $e) {
            $prompt = '';
        }

        try {
            $prdJson = File::get($ralphPath.'/prd.json');
            $prd = json_decode($prdJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $prd = null;
            }
        } catch (\Exception $e) {
            $prd = null;
        }

        try {
            $progress = File::get($ralphPath.'/progress.txt');
        } catch (\Exception $e) {
            $progress = '';
        }

        try {
            $guardrails = File::exists($ralphPath.'/guardrails.md')
                ? File::get($ralphPath.'/guardrails.md')
                : '';
        } catch (\Exception $e) {
            $guardrails = '';
        }

        return new RalphState(
            prompt: $prompt,
            prd: $prd,
            progress: $progress,
            guardrails: $guardrails,
        );
    }

    public function updatePrd(Task $task, array $prd): void
    {
        $ralphPath = $this->getRalphPath($task);
        File::put($ralphPath.'/prd.json', json_encode($prd, JSON_PRETTY_PRINT));
    }

    public function appendProgress(Task $task, string $learning): void
    {
        $ralphPath = $this->getRalphPath($task);

        try {
            $current = File::get($ralphPath.'/progress.txt');
        } catch (\Exception $e) {
            $current = '';
        }

        $updated = $current."\n\n".$learning;
        File::put($ralphPath.'/progress.txt', $updated);
    }

    public function appendGuardrail(Task $task, string $guardrail): void
    {
        $ralphPath = $this->getRalphPath($task);
        $current = File::exists($ralphPath.'/guardrails.md') ? File::get($ralphPath.'/guardrails.md') : '';
        $updated = $current."\n\n".$guardrail;
        File::put($ralphPath.'/guardrails.md', $updated);
    }

    public function logActivity(Task $task, array $activity): void
    {
        $ralphPath = $this->getRalphPath($task);
        $logEntry = json_encode($activity)."\n";
        File::append($ralphPath.'/activity.log', $logEntry);
    }

    protected function getRalphPath(Task $task): string
    {
        return $task->getRalphWorkspacePath();
    }

    protected function writePrompt(string $ralphPath, array $config): void
    {
        $template = <<<'MD'
# Ralph Agent Instructions

You are an autonomous coding agent working on a Laravel application.

## Your Environment

- PHP 8.4, Laravel 12, Filament 4, Livewire 3, Tailwind CSS 4, Pest
- Use `php artisan make:` commands to create new files
- Use Eloquent models and relationships, avoid raw `DB::` queries
- Follow existing code conventions - check sibling files before creating new ones

## Your Task

1. Read `.ralph/prd.json` for the product requirements and user stories
2. Read `.ralph/progress.txt` (check **Codebase Patterns** section FIRST)
3. Read `.ralph/guardrails.md` for applicable constraints
4. Check you're on the correct branch: `{{ branchName }}`. If not, create it from main.
5. Pick the **highest priority** user story where `passes: false`
6. Implement that **ONE** story only
7. Run quality checks:
   - `vendor/bin/pint --dirty` (fix code style)
   - Targeted tests only (run only story-specific tests, not full suite)
8. If checks pass, commit ALL changes: `feat: [Story ID] - [Story Title]`
   - The full test suite will run in GitHub CI
9. Update `.ralph/prd.json`: set `passes: true` for the completed story
10. Append learnings to `.ralph/progress.txt`

## Laravel Conventions

- Always use Form Request classes for validation (not inline)
- Use constructor property promotion in `__construct()`
- Always add explicit return type declarations
- Use Eloquent relationships and eager loading (prevent N+1)
- Create factories and seeders for new models
- Use queued jobs for time-consuming operations
- Use `config()` not `env()` outside of config files
- Use named routes and the `route()` function for URL generation

## Filament Conventions

- Use `php artisan make:filament-resource` for new resources
- Use `relationship()` on form components when possible
- Test Filament with `livewire(ListUsers::class)` style assertions
- Ensure authenticated in Filament tests

## Testing

- Write Pest tests (not PHPUnit)
- Use feature tests by default, unit tests only when specifically needed
- Use model factories - check for existing factory states before manual setup
- Test through public interfaces, not implementation details
- For Filament: use Livewire test helpers (`livewire()`, `assertCanSeeTableRecords()`, etc.)

## Progress Report Format

APPEND to `.ralph/progress.txt` (never replace, always append):

```
## [Date] - [Story ID]
- What was implemented
- Files changed
- **Learnings for future iterations:**
  - Patterns discovered
  - Gotchas encountered
  - Useful context
---
```

## Consolidate Patterns

If you discover a **reusable pattern**, add it to the `## Codebase Patterns` section at the TOP of progress.txt:

```
## Codebase Patterns
- Example: Use Pest for all tests, not PHPUnit
- Example: Filament resources live in app/Filament/Resources/
- Example: Always run vendor/bin/pint --dirty before committing
```

Only add patterns that are **general and reusable**, not story-specific.

## Quality Requirements

- ALL commits must pass `vendor/bin/pint --dirty` and targeted story-specific tests
- The full test suite will be verified in GitHub CI
- Do NOT commit broken code
- Keep changes focused and minimal
- Follow existing code patterns in sibling files

## Stop Condition

After completing a user story, check if ALL stories have `passes: true`.

If ALL stories are complete and passing, reply with:
<promise>COMPLETE</promise>

If there are still stories with `passes: false`, end your response normally (another iteration will pick up the next story).

## Important

- Work on ONE story per iteration
- Commit frequently
- Keep CI green
- Read the Codebase Patterns section in progress.txt before starting
- Run `vendor/bin/pint --dirty` before every commit
MD;

        $prompt = str_replace(
            '{{ branchName }}',
            $config['branch_name'] ?? 'main',
            $template
        );

        File::put($ralphPath.'/prompt.md', $prompt);
    }

    protected function writePrd(string $ralphPath, array $stories): void
    {
        $prd = [
            'branchName' => '',
            'userStories' => $stories,
        ];

        File::put($ralphPath.'/prd.json', json_encode($prd, JSON_PRETTY_PRINT));
    }

    protected function writeProgress(string $ralphPath): void
    {
        $template = <<<'TXT'
# Ralph Progress Log
Started: {{ date }}

## Codebase Patterns
<!-- Patterns accumulate here -->

## Iteration History
<!-- Learnings append here -->
TXT;

        $progress = str_replace('{{ date }}', now()->format('Y-m-d H:i:s'), $template);
        File::put($ralphPath.'/progress.txt', $progress);
    }

    protected function guardrailsTemplate(): string
    {
        return <<<'MD'
# Ralph Guardrails

## Database Safety
- NEVER modify user passwords, API keys, tokens, or credentials
- NEVER run destructive migrations (migrate:fresh, reset, db:wipe)
- Read-only queries for debugging, writes only for the feature being implemented

## Git Safety
- NEVER force push
- NEVER push to main/master directly
- NEVER amend published commits
- Always create feature commits, not amend

## Code Safety
- NEVER remove existing tests unless replacing them
- NEVER modify .env files
- NEVER change authentication/authorization without explicit story requirement
MD;
    }
}
