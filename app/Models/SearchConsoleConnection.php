<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchConsoleConnection extends Model
{
    /** @use HasFactory<\Database\Factories\SearchConsoleConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'credentials_json',
    ];

    protected function casts(): array
    {
        return [
            'credentials_json' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sanitized name suitable for use as an environment variable key.
     */
    public function getEnvKeyName(): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '_', $this->name));
    }

    /**
     * Get the file path where the credentials JSON should be stored for the MCP server.
     */
    public function getCredentialsFilePath(): string
    {
        return storage_path("app/private/search-console/{$this->id}.json");
    }

    /**
     * Extract the client email from the credentials JSON.
     */
    public function getClientEmail(): ?string
    {
        $decoded = json_decode($this->credentials_json, true);

        return $decoded['client_email'] ?? null;
    }
}
