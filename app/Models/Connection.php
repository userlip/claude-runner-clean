<?php

namespace App\Models;

use App\Enums\ConnectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'credentials',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConnectionType::class,
            'credentials' => 'encrypted',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repositories(): HasMany
    {
        return $this->user->repositories();
    }

    /**
     * GitHub backward-compat: access_token is stored in credentials.
     */
    public function getAccessTokenAttribute(): ?string
    {
        return $this->credentials;
    }

    /**
     * GitHub backward-compat: github_username from metadata.
     */
    public function getGithubUsernameAttribute(): ?string
    {
        return $this->metadata['github_username'] ?? null;
    }

    /**
     * GitHub backward-compat: github_user_id from metadata.
     */
    public function getGithubUserIdAttribute(): ?string
    {
        return $this->metadata['github_user_id'] ?? null;
    }

    /**
     * GitHub backward-compat: scopes from metadata.
     */
    public function getScopesAttribute(): ?array
    {
        return $this->metadata['scopes'] ?? null;
    }

    /**
     * GA/SC backward-compat: credentials_json is stored in credentials.
     */
    public function getCredentialsJsonAttribute(): ?string
    {
        return $this->credentials;
    }

    /**
     * GA backward-compat: property_id from metadata.
     */
    public function getPropertyIdAttribute(): ?string
    {
        return $this->metadata['property_id'] ?? null;
    }

    /**
     * Get the sanitized name suitable for use as an environment variable key.
     */
    public function getEnvKeyName(): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '_', $this->name ?? ''));
    }

    /**
     * Get the file path where the credentials JSON should be stored for the MCP server.
     */
    public function getCredentialsFilePath(): string
    {
        $dir = match ($this->type) {
            ConnectionType::GoogleAnalytics => 'google-analytics',
            ConnectionType::SearchConsole => 'search-console',
            default => 'connections',
        };

        return storage_path("app/private/{$dir}/{$this->id}.json");
    }

    /**
     * Extract the client email from the credentials JSON.
     */
    public function getClientEmail(): ?string
    {
        $decoded = json_decode($this->credentials ?? '', true);

        return $decoded['client_email'] ?? null;
    }
}
