<?php

namespace App\Models;

use App\Enums\TaskStatus;
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
        'site_id',
        'ai_provider_id',
        'scrapp_api_id',
        'workspace_path',
        'session_id',
        'status',
        'init_status',
        'ran_composer_install',
        'ran_npm_install',
        'ran_npm_build',
        'is_compacting',
        'needs_compact',
        'compaction_count',
        'max_turns',
        'question_responses',
        'started_at',
        'completed_at',
        'last_viewed_at',
        'last_message_at',
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
            'compaction_count' => 'integer',
            'max_turns' => 'integer',
            'question_responses' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'last_message_at' => 'datetime',
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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function scrappApi(): BelongsTo
    {
        return $this->belongsTo(ScrappApi::class);
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
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->updateProposalExecution(true);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => TaskStatus::Failed,
            'completed_at' => now(),
        ]);

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
}
