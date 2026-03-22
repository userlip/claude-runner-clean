<?php

namespace App\Models;

use App\Enums\MajorUpgradeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MajorUpgradeRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'github_pr_number',
        'status',
        'source_pr_url',
        'source_pr_sha',
        'work_branch',
        'upgrade_summary',
        'error_message',
        'review_site_url',
        'review_site_id',
        'created_by_task_id',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MajorUpgradeStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
