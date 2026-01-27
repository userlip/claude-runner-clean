<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'user_id',
        'ai_provider_id',
        'name',
        'prompt',
        'cron_expression',
        'builder_config',
        'is_active',
        'delete_after_minutes',
        'last_run_at',
        'last_run_status',
        'last_task_id',
    ];

    protected function casts(): array
    {
        return [
            'builder_config' => 'array',
            'is_active' => 'boolean',
            'delete_after_minutes' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function lastTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'last_task_id');
    }
}
