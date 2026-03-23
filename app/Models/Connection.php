<?php

namespace App\Models;

use App\Enums\ConnectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
