<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GitHubConnection extends Model
{
    use HasFactory;

    protected $table = 'github_connections';

    protected $fillable = [
        'user_id',
        'access_token',
        'github_user_id',
        'github_username',
        'scopes',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'scopes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }
}
