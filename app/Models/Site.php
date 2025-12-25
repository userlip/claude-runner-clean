<?php

namespace App\Models;

use App\Enums\SiteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'domain',
        'path',
        'ploi_site_id',
        'branch',
        'php_version',
        'web_directory',
        'isolated_user',
        'database_name',
        'deploy_script',
        'status',
        'error_message',
        'synced_from_ploi',
    ];

    protected function casts(): array
    {
        return [
            'isolated_user' => 'boolean',
            'synced_from_ploi' => 'boolean',
            'status' => SiteStatus::class,
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isActive(): bool
    {
        return $this->status === SiteStatus::Active;
    }

    public function markAsProvisioning(): void
    {
        $this->update(['status' => SiteStatus::Provisioning]);
    }

    public function markAsActive(string $path, ?string $ploiSiteId = null): void
    {
        $this->update([
            'status' => SiteStatus::Active,
            'path' => $path,
            'ploi_site_id' => $ploiSiteId,
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => SiteStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }
}
