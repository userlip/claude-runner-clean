<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DirectorySubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'directory_id',
        'product',
        'status',
        'submitted_at',
        'listed_at',
        'listing_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'listed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DirectorySubmission $submission) {
            $submission->uuid ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function directory(): BelongsTo
    {
        return $this->belongsTo(PromotionDirectory::class, 'directory_id');
    }

    public function markAsSubmitted(): void
    {
        $this->update([
            'status' => SubmissionStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function markAsListed(?string $listingUrl = null): void
    {
        $this->update([
            'status' => SubmissionStatus::Listed,
            'listed_at' => now(),
            'listing_url' => $listingUrl,
        ]);
    }

    public function markAsRejected(?string $reason = null): void
    {
        $this->update([
            'status' => SubmissionStatus::Rejected,
            'notes' => $reason,
        ]);
    }
}
