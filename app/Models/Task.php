<?php

namespace App\Models;

use App\DataObjects\RalphState;
use App\Enums\TaskStatus;
use App\Events\TaskStatusUpdated;
use App\Jobs\DeleteTaskJob;
use App\Jobs\RunClaudeMessageJob;
use App\Jobs\RunCodexMessageJob;
use App\Jobs\SyncAsanaTaskCompletion;
use App\Services\RalphWorkspaceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'repository_id',
        'task_schedule_id',
        'site_id',
        'ai_provider_id',
        'asana_task_id',
        'workspace_path',
        'session_id',
        'status',
        'init_status',
        'ran_composer_install',
        'ran_npm_install',
        'ran_npm_build',
        'is_compacting',
        'needs_compact',
        'has_active_subagents',
        'compaction_count',
        'max_turns',
        'question_responses',
        'session_metadata',
        'todos',
        'started_at',
        'completed_at',
        'last_viewed_at',
        'last_message_at',
        'ralph_enabled',
        'ralph_iteration',
        'ralph_max_iterations',
        'ralph_anchor_path',
        'ralph_branch_name',
        'ralph_rotation_threshold',
        'ralph_model_rotation',
        'ralph_gutter_count',
        'ralph_stopped_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'ran_composer_install' => 'boolean',
            'ran_npm_install' => 'boolean',
            'ran_npm_build' => 'boolean',
            'is_compacting' => 'boolean',
            'needs_compact' => 'boolean',
            'has_active_subagents' => 'boolean',
            'compaction_count' => 'integer',
            'max_turns' => 'integer',
            'question_responses' => 'array',
            'session_metadata' => 'array',
            'todos' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'last_message_at' => 'datetime',
            'ralph_enabled' => 'boolean',
            'ralph_iteration' => 'integer',
            'ralph_max_iterations' => 'integer',
            'ralph_rotation_threshold' => 'decimal:2',
            'ralph_model_rotation' => 'array',
            'ralph_gutter_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->uuid ??= Str::uuid();
            $task->session_id ??= Str::uuid();
            $task->ai_provider_id ??= AiProvider::getDefault()?->id;
        });

        static::deleting(function (Task $task) {
            if ($task->workspace_path && File::isDirectory($task->workspace_path)) {
                File::deleteDirectory($task->workspace_path);
            }
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

    public function taskSchedule(): BelongsTo
    {
        return $this->belongsTo(TaskSchedule::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getWorkingDirectoryAttribute(): ?string
    {
        if ($this->workspace_path) {
            return $this->workspace_path;
        }

        if ($this->site?->path) {
            return $this->site->path;
        }

        // General chat mode - use home directory
        return '/home/ploi';
    }

    public function isInWorkspace(): bool
    {
        return $this->workspace_path !== null;
    }

    public function isGeneralChat(): bool
    {
        return $this->repository_id === null;
    }

    public function isSystemTask(): bool
    {
        // Check if linked to a SecurityRun (each PR has its own task)
        if (SecurityRun::where('task_id', $this->id)->exists()) {
            return true;
        }

        // Also check by title pattern for security tasks (both old and new formats)
        $title = $this->title ?? '';

        return str_starts_with($title, 'Security PR #') || str_starts_with($title, 'Security Management:');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function dispatchMessage(Message $userMessage, bool $continue = false, string $queue = 'default'): void
    {
        if ($this->aiProvider?->isCodex()) {
            RunCodexMessageJob::dispatch($this, $userMessage, continue: $continue)->onQueue($queue);

            return;
        }

        RunClaudeMessageJob::dispatch($this, $userMessage, continue: $continue)->onQueue($queue);
    }

    public function isRunning(): bool
    {
        return $this->status === TaskStatus::Running;
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);

        TaskStatusUpdated::dispatch($this);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
            'has_active_subagents' => false,
        ]);

        TaskStatusUpdated::dispatch($this);

        // Sync completion to Asana if linked
        if ($this->asana_task_id) {
            SyncAsanaTaskCompletion::dispatch($this);
        }

        $this->handleScheduleCompletion();
        $this->updateProposalExecution(true);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => TaskStatus::Failed,
            'completed_at' => now(),
        ]);

        TaskStatusUpdated::dispatch($this);

        $this->handleScheduleCompletion();
        $this->updateProposalExecution(false);
    }

    public function markAsWaitingForInput(): void
    {
        $this->update([
            'status' => TaskStatus::WaitingForInput,
        ]);
    }

    public function isWaitingForInput(): bool
    {
        return $this->status === TaskStatus::WaitingForInput;
    }

    public function updateProposalExecution(bool $success): void
    {
        $proposal = Proposal::where('executed_task_id', $this->id)->first();

        if ($proposal) {
            $proposal->markExecutionComplete($success);
        }
    }

    public function markAsViewed(): void
    {
        $this->update(['last_viewed_at' => now()]);
    }

    public function hasUnreadReply(): bool
    {
        // No last_viewed_at means never viewed - check if there are any assistant messages
        if (! $this->last_viewed_at) {
            return $this->messages()
                ->where('role', \App\Enums\MessageRole::Assistant)
                ->exists();
        }

        // Check if there's an assistant message after last viewed and chat is not running
        return ! $this->isRunning() && $this->messages()
            ->where('role', \App\Enums\MessageRole::Assistant)
            ->where('created_at', '>', $this->last_viewed_at)
            ->exists();
    }

    public function isInitializing(): bool
    {
        return $this->init_status !== null && $this->init_status !== 'completed';
    }

    public function setInitStatus(string $status): void
    {
        $this->update(['init_status' => $status]);
    }

    // Ralph helper methods

    public function isRalphMode(): bool
    {
        return $this->ralph_enabled === true;
    }

    public function shouldRotateContext(): bool
    {
        if (! $this->isRalphMode()) {
            return false;
        }

        $tokensUsed = $this->messages()->sum('tokens_in');
        $contextWindow = $this->aiProvider?->context_window ?? 200000;

        return ($tokensUsed / $contextWindow) >= $this->ralph_rotation_threshold;
    }

    public function getNextRalphProvider(): ?AiProvider
    {
        if (empty($this->ralph_model_rotation)) {
            return null;
        }

        $providers = $this->ralph_model_rotation;
        $index = $this->ralph_iteration % count($providers);

        return AiProvider::find($providers[$index]);
    }

    public function getRalphWorkspacePath(): string
    {
        return $this->working_directory.'/.ralph';
    }

    public function getRalphState(): RalphState
    {
        return app(RalphWorkspaceService::class)->readState($this);
    }

    protected function handleScheduleCompletion(): void
    {
        if (! $this->taskSchedule) {
            return;
        }

        $this->taskSchedule->update([
            'last_run_status' => $this->status->value,
            'last_task_id' => $this->id,
        ]);

        if ($this->taskSchedule->delete_after_minutes) {
            DeleteTaskJob::dispatch($this->id)
                ->delay(now()->addMinutes($this->taskSchedule->delete_after_minutes));
        }
    }
}
