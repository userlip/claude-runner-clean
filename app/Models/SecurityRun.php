<?php

namespace App\Models;

use App\Enums\SecurityRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'task_id',
        'github_pr_id',
        'github_pr_number',
        'pr_title',
        'from_version',
        'to_version',
        'risk_level',
        'status',
        'decision_summary',
        'merge_commit_sha',
        'last_checked_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => SecurityRunStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $run) {
            // Extract versions from PR title when saving
            if ($run->isDirty('pr_title')) {
                $run->from_version = null;
                $run->to_version = null;

                if (preg_match('/from\s+([\d.]+(?:-[\w.]+)?)/i', $run->pr_title, $matches)) {
                    $run->from_version = $matches[1];
                }

                if (preg_match('/to\s+([\d.]+(?:-[\w.]+)?)/i', $run->pr_title, $matches)) {
                    $run->to_version = $matches[1];
                }
            }
        });
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
