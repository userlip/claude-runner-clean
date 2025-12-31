<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScrappApi extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'route_prefix',
        'rapidapi_slug',
        'is_active',
        'last_tested_at',
        'last_test_result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function latestTask(): HasOne
    {
        return $this->hasOne(Task::class)->latestOfMany();
    }
}
