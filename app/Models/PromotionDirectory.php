<?php

namespace App\Models;

use App\Enums\DirectoryCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PromotionDirectory extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'url',
        'category',
        'submission_type',
        'submission_url',
        'requirements',
        'suitable_products',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => DirectoryCategory::class,
            'requirements' => 'array',
            'suitable_products' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PromotionDirectory $directory) {
            $directory->uuid ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DirectorySubmission::class, 'directory_id');
    }

    public function isFree(): bool
    {
        return $this->submission_type === 'free';
    }

    public function isPaid(): bool
    {
        return $this->submission_type === 'paid';
    }

    public function isInviteOnly(): bool
    {
        return $this->submission_type === 'invite_only';
    }

    public function isSuitableFor(string $product): bool
    {
        if (empty($this->suitable_products)) {
            return true;
        }

        return in_array($product, $this->suitable_products);
    }
}
