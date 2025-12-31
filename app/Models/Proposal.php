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
        'telegram_message_id',
        'approved_at',
        'rejected_at',
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
        $this->update([
            'status' => ProposalStatus::Approved,
            'approved_at' => now(),
        ]);

        // Trigger autonomous execution
        $service = app(\App\Services\ProposalExecutionService::class);
        $service->execute($this);
    }

    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => ProposalStatus::Rejected,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
        ]);
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
