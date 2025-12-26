<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryEnvConfig extends Model
{
    protected $fillable = [
        'repository_id',
        'name',
        'content',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Set this config as the default, unsetting any other defaults for the same repository.
     */
    public function setAsDefault(): void
    {
        // Unset other defaults for this repository
        static::where('repository_id', $this->repository_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
