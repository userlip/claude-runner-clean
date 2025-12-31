# Autonomous Work Loop Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** When a Proposal is approved, automatically create and execute a Task with skill-aware prompts.

**Architecture:** Proposal approval triggers ProposalExecutionService which creates a Task with workspace clone, generates a skill-aware prompt from proposed_action, and dispatches RunClaudeMessageJob for autonomous execution.

**Tech Stack:** Laravel 12, PHP 8.4, Filament v4, existing Task/Message/Job infrastructure

---

## Task 1: Add project_key to repositories table

**Files:**
- Create: `database/migrations/2025_12_31_210000_add_project_key_to_repositories_table.php`
- Modify: `app/Models/Repository.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->string('project_key')->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn('project_key');
        });
    }
};
```

**Step 2: Add to Repository model fillable**

Add `'project_key'` to the `$fillable` array in `app/Models/Repository.php`.

**Step 3: Add helper method to Repository model**

```php
public static function findByProjectKey(string $projectKey): ?self
{
    return static::where('project_key', $projectKey)->first();
}
```

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*project_key* app/Models/Repository.php
git commit -m "feat: add project_key to repositories for proposal mapping"
```

---

## Task 2: Create ProposalType enum

**Files:**
- Create: `app/Enums/ProposalType.php`

**Step 1: Create the enum**

```php
<?php

namespace App\Enums;

enum ProposalType: string
{
    case ImplementApi = 'implement_api';
    case FixScraper = 'fix_scraper';
    case ResearchOpportunity = 'research_opportunity';
    case SeoImprovement = 'seo_improvement';
    case PublishApi = 'publish_api';
    case DeployEndpoint = 'deploy_endpoint';
    case FeatureRequest = 'feature_request';
    case BugFix = 'bug_fix';
    case Refactor = 'refactor';
    case Documentation = 'documentation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ImplementApi => 'Implement API',
            self::FixScraper => 'Fix Scraper',
            self::ResearchOpportunity => 'Research Opportunity',
            self::SeoImprovement => 'SEO Improvement',
            self::PublishApi => 'Publish API',
            self::DeployEndpoint => 'Deploy Endpoint',
            self::FeatureRequest => 'Feature Request',
            self::BugFix => 'Bug Fix',
            self::Refactor => 'Refactor',
            self::Documentation => 'Documentation',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ImplementApi => 'heroicon-o-code-bracket',
            self::FixScraper => 'heroicon-o-wrench-screwdriver',
            self::ResearchOpportunity => 'heroicon-o-magnifying-glass',
            self::SeoImprovement => 'heroicon-o-chart-bar',
            self::PublishApi => 'heroicon-o-cloud-arrow-up',
            self::DeployEndpoint => 'heroicon-o-rocket-launch',
            self::FeatureRequest => 'heroicon-o-light-bulb',
            self::BugFix => 'heroicon-o-bug-ant',
            self::Refactor => 'heroicon-o-arrow-path',
            self::Documentation => 'heroicon-o-document-text',
            self::Other => 'heroicon-o-question-mark-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ImplementApi => 'primary',
            self::FixScraper => 'danger',
            self::ResearchOpportunity => 'info',
            self::SeoImprovement => 'success',
            self::PublishApi => 'warning',
            self::DeployEndpoint => 'success',
            self::FeatureRequest => 'primary',
            self::BugFix => 'danger',
            self::Refactor => 'gray',
            self::Documentation => 'gray',
            self::Other => 'gray',
        };
    }

    /**
     * Get the skills that should be invoked for this proposal type.
     *
     * @return array<string>
     */
    public function getRequiredSkills(): array
    {
        return match ($this) {
            self::ImplementApi => [
                'api-discovery',
                'github-api-research',
                'proxy-viability-test',
                'superpowers:writing-plans',
                'scrappa-deploy-and-test',
                'scrappa-endpoint-testing',
            ],
            self::FixScraper => [
                'proxy-viability-test',
                'superpowers:systematic-debugging',
            ],
            self::ResearchOpportunity => [
                'api-discovery',
                'market-research',
            ],
            self::SeoImprovement => [
                'market-research',
            ],
            self::PublishApi => [
                'rapidapi-publishing',
            ],
            self::DeployEndpoint => [
                'scrappa-deploy-and-test',
                'scrappa-endpoint-testing',
            ],
            self::FeatureRequest => [
                'superpowers:brainstorming',
                'superpowers:writing-plans',
            ],
            self::BugFix => [
                'superpowers:systematic-debugging',
            ],
            self::Refactor => [
                'superpowers:writing-plans',
                'superpowers:requesting-code-review',
            ],
            self::Documentation => [],
            self::Other => [],
        };
    }
}
```

**Step 2: Verify syntax**

```bash
php -l app/Enums/ProposalType.php
```

**Step 3: Commit**

```bash
git add app/Enums/ProposalType.php
git commit -m "feat: add ProposalType enum with skill mappings"
```

---

## Task 3: Add type column to proposals table

**Files:**
- Create: `database/migrations/2025_12_31_210001_add_type_to_proposals_table.php`
- Modify: `app/Models/Proposal.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('type')->default('other')->after('project');
            $table->foreignId('executed_task_id')->nullable()->after('task_id')->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropForeign(['executed_task_id']);
            $table->dropColumn(['type', 'executed_task_id']);
        });
    }
};
```

**Step 2: Update Proposal model**

Add to `$fillable`:
```php
'type',
'executed_task_id',
```

Add to `casts()`:
```php
'type' => \App\Enums\ProposalType::class,
```

Add relationship:
```php
public function executedTask(): BelongsTo
{
    return $this->belongsTo(Task::class, 'executed_task_id');
}
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*add_type_to_proposals* app/Models/Proposal.php
git commit -m "feat: add type and executed_task_id to proposals"
```

---

## Task 4: Create ProposalExecutionService

**Files:**
- Create: `app/Services/ProposalExecutionService.php`

**Step 1: Create the service**

```php
<?php

namespace App\Services;

use App\Enums\ProposalType;
use App\Jobs\CloneRepositoryJob;
use App\Jobs\RunClaudeMessageJob;
use App\Models\AiProvider;
use App\Models\Proposal;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Support\Str;

class ProposalExecutionService
{
    public function execute(Proposal $proposal): Task
    {
        // Find repository by project key
        $repository = Repository::findByProjectKey($proposal->project);

        // Use GLM provider for autonomous tasks
        $glmProvider = AiProvider::where('name', 'glm')->where('is_active', true)->first();

        // Create workspace path if repository exists
        $workspacePath = null;
        if ($repository) {
            $workspacePath = '/home/ploi/workspaces/'.Str::slug($repository->name).'-'.Str::random(8);
        }

        // Create the task
        $task = Task::create([
            'title' => $proposal->title,
            'status' => \App\Enums\TaskStatus::Pending,
            'ai_provider_id' => $glmProvider?->id ?? AiProvider::getDefault()?->id,
            'repository_id' => $repository?->id,
            'workspace_path' => $workspacePath,
        ]);

        // Generate the prompt with skill instructions
        $prompt = $this->generatePrompt($proposal);

        // Create the initial message
        $task->messages()->create([
            'role' => \App\Enums\MessageRole::User,
            'content' => $prompt,
        ]);

        // Link the task to the proposal
        $proposal->update(['executed_task_id' => $task->id]);

        // If repository exists, clone it first then run Claude
        if ($repository && $workspacePath) {
            CloneRepositoryJob::withChain([
                new RunClaudeMessageJob($task),
            ])->dispatch($task);
        } else {
            // No repository - just run Claude directly
            RunClaudeMessageJob::dispatch($task);
        }

        return $task;
    }

    protected function generatePrompt(Proposal $proposal): string
    {
        $type = $proposal->type ?? ProposalType::Other;
        $skills = $type->getRequiredSkills();
        $proposedAction = $proposal->proposed_action ?? [];

        $prompt = "## Task: {$proposal->title}\n\n";

        // Add description
        if ($proposal->description) {
            $prompt .= "### Description\n{$proposal->description}\n\n";
        }

        // Add required skills section
        if (! empty($skills)) {
            $prompt .= "### Required Skills (INVOKE THESE)\n";
            $prompt .= "You MUST use these skills in order. Do not skip any skill.\n\n";
            foreach ($skills as $index => $skill) {
                $num = $index + 1;
                $prompt .= "{$num}. `/{$skill}`\n";
            }
            $prompt .= "\n";
        }

        // Add context from proposed_action
        if (! empty($proposedAction)) {
            $prompt .= "### Context\n";

            if (isset($proposedAction['target'])) {
                $prompt .= "- **Target:** {$proposedAction['target']}\n";
            }

            if (isset($proposedAction['endpoint'])) {
                $prompt .= "- **Endpoint:** {$proposedAction['endpoint']}\n";
            }

            if (isset($proposedAction['service'])) {
                $prompt .= "- **Service:** {$proposedAction['service']}\n";
            }

            if (isset($proposedAction['files_to_modify']) && is_array($proposedAction['files_to_modify'])) {
                $files = implode(', ', $proposedAction['files_to_modify']);
                $prompt .= "- **Files to modify:** {$files}\n";
            }

            if (isset($proposedAction['instructions'])) {
                $prompt .= "\n### Instructions\n{$proposedAction['instructions']}\n";
            }

            $prompt .= "\n";
        }

        // Add project context
        $prompt .= "### Project\n";
        $prompt .= "- **Project:** {$proposal->project}\n";
        $prompt .= "- **Priority:** {$proposal->priority->value}\n";
        $prompt .= "\n";

        // Add completion requirements
        $prompt .= "### Completion Requirements\n";
        $prompt .= "1. Follow all skills in order - do not skip any\n";
        $prompt .= "2. Create Proposals for any follow-up work discovered\n";
        $prompt .= "3. Commit your changes with descriptive messages\n";
        $prompt .= "4. Report completion status when done\n";

        return $prompt;
    }
}
```

**Step 2: Verify syntax**

```bash
php -l app/Services/ProposalExecutionService.php
```

**Step 3: Commit**

```bash
git add app/Services/ProposalExecutionService.php
git commit -m "feat: add ProposalExecutionService for autonomous task execution"
```

---

## Task 5: Update Proposal::approve() to trigger execution

**Files:**
- Modify: `app/Models/Proposal.php`

**Step 1: Update the approve method**

Find the `approve()` method and update it:

```php
public function approve(): void
{
    $this->update([
        'status' => ProposalStatus::Approved,
        'approved_at' => now(),
    ]);

    // Trigger autonomous execution
    $service = app(\App\Services\ProposalExecutionService::class);
    $service->execute($this);
}
```

**Step 2: Verify syntax**

```bash
php -l app/Models/Proposal.php
```

**Step 3: Commit**

```bash
git add app/Models/Proposal.php
git commit -m "feat: trigger task execution when proposal is approved"
```

---

## Task 6: Add execution status to ProposalResource

**Files:**
- Modify: `app/Filament/Resources/ProposalResource.php`

**Step 1: Add executed task column to table**

In the `table()` method, add after the status column:

```php
Tables\Columns\TextColumn::make('executedTask.status')
    ->label('Execution')
    ->badge()
    ->color(fn ($state) => match ($state?->value ?? null) {
        'pending' => 'gray',
        'running' => 'warning',
        'completed' => 'success',
        'failed' => 'danger',
        default => 'gray',
    })
    ->placeholder('Not started'),
```

**Step 2: Add type column to table**

```php
Tables\Columns\TextColumn::make('type')
    ->badge()
    ->color(fn (\App\Enums\ProposalType $state) => $state->color()),
```

**Step 3: Add type field to form**

```php
Forms\Components\Select::make('type')
    ->options(\App\Enums\ProposalType::class)
    ->required()
    ->default('other'),
```

**Step 4: Commit**

```bash
git add app/Filament/Resources/ProposalResource.php
git commit -m "feat: show execution status and type in ProposalResource"
```

---

## Task 7: Send Telegram notification when task starts

**Files:**
- Modify: `app/Services/ProposalExecutionService.php`

**Step 1: Add notification after task creation**

After `$proposal->update(['executed_task_id' => $task->id]);`, add:

```php
// Send Telegram notification
$this->sendStartNotification($proposal, $task);
```

**Step 2: Add the notification method**

```php
protected function sendStartNotification(Proposal $proposal, Task $task): void
{
    $token = config('services.telegram.bot_token');
    $chatId = config('services.telegram.chat_id');

    if (! $token || ! $chatId) {
        return;
    }

    $message = "🚀 *Task Started*\n\n";
    $message .= "*Proposal:* {$proposal->title}\n";
    $message .= "*Project:* {$proposal->project}\n";
    $message .= "*Type:* {$proposal->type->label()}\n\n";
    $message .= "Task #{$task->id} is now executing autonomously.";

    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown',
    ]);
}
```

**Step 3: Add use statement**

```php
use Illuminate\Support\Facades\Http;
```

**Step 4: Commit**

```bash
git add app/Services/ProposalExecutionService.php
git commit -m "feat: send Telegram notification when task starts"
```

---

## Task 8: Update Filament ProposalResource form for proposed_action

**Files:**
- Modify: `app/Filament/Resources/ProposalResource.php`

**Step 1: Update proposed_action field to use structured fields**

Replace the simple JSON field with a structured fieldset:

```php
Forms\Components\Fieldset::make('Proposed Action')
    ->schema([
        Forms\Components\TextInput::make('proposed_action.target')
            ->label('Target')
            ->helperText('e.g., YouTube Shorts, Trustpilot Reviews'),

        Forms\Components\TextInput::make('proposed_action.endpoint')
            ->label('Endpoint Hint')
            ->helperText('e.g., /api/v2/shorts'),

        Forms\Components\TextInput::make('proposed_action.service')
            ->label('Service')
            ->helperText('e.g., youtube, trustpilot'),

        Forms\Components\TagsInput::make('proposed_action.files_to_modify')
            ->label('Files to Modify')
            ->helperText('Paths of files likely to be modified'),

        Forms\Components\Textarea::make('proposed_action.instructions')
            ->label('Additional Instructions')
            ->rows(3),
    ])
    ->columns(2),
```

**Step 2: Commit**

```bash
git add app/Filament/Resources/ProposalResource.php
git commit -m "feat: structured proposed_action fields in ProposalResource"
```

---

## Task 9: Create proposal:execute command for manual execution

**Files:**
- Create: `app/Console/Commands/ProposalExecuteCommand.php`

**Step 1: Create the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\Proposal;
use App\Services\ProposalExecutionService;
use Illuminate\Console\Command;

class ProposalExecuteCommand extends Command
{
    protected $signature = 'proposal:execute
                            {id : The proposal ID or UUID to execute}
                            {--force : Execute even if already approved}';

    protected $description = 'Manually execute an approved proposal';

    public function handle(ProposalExecutionService $service): int
    {
        $identifier = $this->argument('id');

        $proposal = Proposal::where('id', $identifier)
            ->orWhere('uuid', $identifier)
            ->first();

        if (! $proposal) {
            $this->error("Proposal not found: {$identifier}");

            return self::FAILURE;
        }

        if ($proposal->status !== \App\Enums\ProposalStatus::Approved && ! $this->option('force')) {
            $this->error("Proposal is not approved. Use --force to execute anyway.");

            return self::FAILURE;
        }

        if ($proposal->executed_task_id && ! $this->option('force')) {
            $this->error("Proposal already has an executed task. Use --force to create a new one.");

            return self::FAILURE;
        }

        $this->info("Executing proposal: {$proposal->title}");

        $task = $service->execute($proposal);

        $this->info("Task created: #{$task->id}");
        $this->info("Workspace: {$task->workspace_path}");

        return self::SUCCESS;
    }
}
```

**Step 2: Verify syntax**

```bash
php -l app/Console/Commands/ProposalExecuteCommand.php
```

**Step 3: Commit**

```bash
git add app/Console/Commands/ProposalExecuteCommand.php
git commit -m "feat: add proposal:execute command for manual execution"
```

---

## Task 10: Seed project_key for existing repositories

**Files:**
- Create: `database/seeders/RepositoryProjectKeySeeder.php`

**Step 1: Create the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Repository;
use Illuminate\Database\Seeder;

class RepositoryProjectKeySeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            'scrappa' => ['scrappa', 'scrappa-api', 'scrappa.io'],
            'lto2' => ['lto2', 'lto2-api', 'lto2-backend'],
            'rezensionsheld' => ['rezensionsheld', 'review-hero'],
            'claude_runner' => ['claude-runner', 'claude-runner.marin.sh'],
        ];

        foreach ($mappings as $projectKey => $possibleNames) {
            foreach ($possibleNames as $name) {
                Repository::where('name', 'LIKE', "%{$name}%")
                    ->whereNull('project_key')
                    ->update(['project_key' => $projectKey]);
            }
        }

        $this->command->info('Project keys assigned to repositories.');
    }
}
```

**Step 2: Run the seeder**

```bash
php artisan db:seed --class=RepositoryProjectKeySeeder
```

**Step 3: Commit**

```bash
git add database/seeders/RepositoryProjectKeySeeder.php
git commit -m "feat: add RepositoryProjectKeySeeder for project_key mapping"
```

---

## Task 11: Run all migrations and verify

**Step 1: Run migrations**

```bash
php artisan migrate
```

**Step 2: Verify proposal:execute command exists**

```bash
php artisan proposal:execute --help
```

**Step 3: Verify ProposalType enum**

```bash
php artisan tinker --execute="var_dump(\App\Enums\ProposalType::ImplementApi->getRequiredSkills());"
```

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete Phase 4 Autonomous Work Loop implementation"
```

---

## Summary

This implementation enables:

1. **Proposal → Task Flow**: When a proposal is approved, a Task is automatically created and executed
2. **Skill-Aware Prompts**: Each proposal type has associated skills that are included in the prompt
3. **Workspace Isolation**: Each task gets its own cloned workspace (Option A)
4. **Project Mapping**: Proposals map to repositories via `project_key`
5. **Telegram Notifications**: Notifications sent when tasks start
6. **Manual Override**: `proposal:execute` command for manual execution

### Proposal Types and Skills

| Type | Skills |
|------|--------|
| implement_api | api-discovery, github-api-research, proxy-viability-test, writing-plans, deploy-and-test, endpoint-testing |
| fix_scraper | proxy-viability-test, systematic-debugging |
| research_opportunity | api-discovery, market-research |
| publish_api | rapidapi-publishing |
| deploy_endpoint | deploy-and-test, endpoint-testing |
| feature_request | brainstorming, writing-plans |
| bug_fix | systematic-debugging |
