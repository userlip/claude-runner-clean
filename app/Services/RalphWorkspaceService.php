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

        return new RalphState(
            prompt: File::get($ralphPath.'/prompt.md'),
            prd: json_decode(File::get($ralphPath.'/prd.json'), true),
            progress: File::get($ralphPath.'/progress.txt'),
            guardrails: File::exists($ralphPath.'/guardrails.md')
                ? File::get($ralphPath.'/guardrails.md')
                : '',
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
        $current = File::get($ralphPath.'/progress.txt');
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
        return $task->getRalphPath();
    }

    protected function writePrompt(string $ralphPath, array $config): void
    {
        $template = <<<'MD'
# Ralph Agent Instructions

## Your Task

1. Read `.ralph/prd.json`
2. Read `.ralph/progress.txt` (check Codebase Patterns first)
3. Read `.ralph/guardrails.md` (check for applicable constraints)
4. Check you're on the correct branch: `{{ branchName }}`
5. Pick highest priority story where `passes: false`
6. Implement that ONE story only
7. Run verification: `{{ verificationCommand }}`
8. If verification passes, commit your changes: `feat: [ID] - [Title]`
9. Update `.ralph/prd.json`: set `passes: true` for the completed story
10. Append learnings to `.ralph/progress.txt` in this format:

## [Date] - [Story ID]
- What was implemented
- Files changed
- **Learnings:**
  - Patterns discovered
  - Gotchas encountered

## Stop Condition

If ALL stories pass, reply: <promise>COMPLETE</promise>

Otherwise end normally.
MD;

        $prompt = str_replace(
            ['{{ branchName }}', '{{ verificationCommand }}'],
            [$config['branch_name'] ?? 'main', $config['verification_command'] ?? 'php artisan test'],
            $template
        );

        File::put($ralphPath.'/prompt.md', $prompt);
    }

    protected function writePrd(string $ralphPath, array $stories): void
    {
        $prd = [
            'branchName' => '', // Will be set during initialization
            'verificationCommand' => 'php artisan test',
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

<!-- Guardrails accumulate here as patterns are discovered -->
MD;
    }
}
