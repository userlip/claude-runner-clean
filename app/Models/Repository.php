<?php

namespace App\Models;

use App\Enums\ValueTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'github_id',
        'name',
        'project_key',
        'value_tier',
        'full_name',
        'clone_url',
        'ssh_url',
        'default_branch',
        'private',
        'description',
        'security_management_enabled',
        'claude_code_enabled',
        'ploi_server_id',
        'ploi_server_name',
        'ploi_site_id',
        'ploi_site_domain',
        'security_task_id',
    ];

    protected function casts(): array
    {
        return [
            'github_id' => 'integer',
            'private' => 'boolean',
            'value_tier' => ValueTier::class,
            'security_management_enabled' => 'boolean',
            'claude_code_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function envConfigs(): HasMany
    {
        return $this->hasMany(RepositoryEnvConfig::class);
    }

    public function securityRuns(): HasMany
    {
        return $this->hasMany(SecurityRun::class);
    }

    public function majorUpgradeRuns(): HasMany
    {
        return $this->hasMany(MajorUpgradeRun::class);
    }

    public function securityTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'security_task_id');
    }

    public function defaultEnvConfig(): ?RepositoryEnvConfig
    {
        return $this->envConfigs()->where('is_default', true)->first();
    }

    /**
     * Get the clone URL with authentication token for private repos.
     */
    public function getAuthenticatedCloneUrl(): string
    {
        if (! $this->private) {
            return $this->clone_url;
        }

        // Get the user's GitHub connection
        $connection = GitHubConnection::where('user_id', $this->user_id)->first();

        if (! $connection || ! $connection->access_token) {
            return $this->clone_url;
        }

        // Insert token into HTTPS URL: https://TOKEN@github.com/user/repo.git
        return str_replace(
            'https://github.com/',
            'https://'.$connection->access_token.'@github.com/',
            $this->clone_url
        );
    }

    public static function findByProjectKey(string $projectKey): ?self
    {
        return static::where('project_key', $projectKey)->first();
    }
}
