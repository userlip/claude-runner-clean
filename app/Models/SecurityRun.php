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
        'github_pr_id',
        'github_pr_number',
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

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
