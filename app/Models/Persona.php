<?php

namespace App\Models;

use App\Enums\PersonaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Persona extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'repository_id',
        'ai_provider_id',
        'last_proposal_id',
        'name',
        'slug',
        'description',
        'master_prompt',
        'mcp_guidance',
        'status',
        'is_active',
        'last_run_at',
        'total_runs',
        'total_proposals',
    ];

    protected function casts(): array
    {
        return [
            'status' => PersonaStatus::class,
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'total_runs' => 'integer',
            'total_proposals' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Persona $persona) {
            $persona->slug ??= Str::slug($persona->name);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function lastProposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'last_proposal_id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function taskSchedule(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TaskSchedule::class);
    }

    public function getStoragePath(): string
    {
        return storage_path("personas/{$this->slug}");
    }
}
