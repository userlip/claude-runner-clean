<?php

namespace App\Models;

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Proposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'priority',
        'status',
        'project',
        'type',
        'proposed_action',
        'rejection_reason',
        'task_id',
        'executed_task_id',
        'playbook_id',
        'telegram_message_id',
        'approved_at',
        'rejected_at',
        'rejected_reason',
        'decision_time_seconds',
        'execution_completed_at',
        'execution_success',
        'follow_up_count',
    ];

    protected function casts(): array
    {
        return [
            'priority' => ProposalPriority::class,
            'status' => ProposalStatus::class,
            'type' => ProposalType::class,
            'proposed_action' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'execution_completed_at' => 'datetime',
            'execution_success' => 'boolean',
            'decision_time_seconds' => 'integer',
            'follow_up_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Proposal $proposal) {
            $proposal->uuid ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function executedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'executed_task_id');
    }

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === ProposalStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ProposalStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ProposalStatus::Rejected;
    }

    public function approve(): void
    {
        $decisionTime = $this->created_at->diffInSeconds(now());

        $this->update([
            'status' => ProposalStatus::Approved,
            'approved_at' => now(),
            'decision_time_seconds' => $decisionTime,
        ]);

        // Trigger autonomous execution
        $service = app(\App\Services\ProposalExecutionService::class);
        $service->execute($this);
    }

    public function reject(?string $reason = null): void
    {
        $decisionTime = $this->created_at->diffInSeconds(now());

        $this->update([
            'status' => ProposalStatus::Rejected,
            'rejected_at' => now(),
            'rejected_reason' => $reason,
            'decision_time_seconds' => $decisionTime,
        ]);
    }

    public function markExecutionComplete(bool $success): void
    {
        $this->update([
            'execution_completed_at' => now(),
            'execution_success' => $success,
        ]);

        // Track playbook usage if one was used
        if ($this->playbook_id) {
            $this->playbook?->recordUsage($success);
        }
    }

    public function incrementFollowUpCount(): void
    {
        $this->increment('follow_up_count');
    }

    public function formatForTelegram(): string
    {
        $priorityEmoji = $this->priority->emoji();
        $priorityLabel = $this->priority->label();

        return <<<TEXT
{$priorityEmoji} *New Proposal*

*Title:* {$this->title}
*Project:* `{$this->project}`
*Priority:* {$priorityLabel}

*Description:*
{$this->description}

*ID:* `{$this->id}`
TEXT;
    }
}
