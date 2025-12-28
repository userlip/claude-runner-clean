<?php

namespace App\Models;

use App\Enums\GeneralChatStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GeneralChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'ai_provider_id',
        'session_id',
        'title',
        'working_directory',
        'status',
        'started_at',
        'completed_at',
        'last_viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GeneralChatStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GeneralChat $chat) {
            $chat->uuid ??= Str::uuid();
            $chat->session_id ??= Str::uuid();
            $chat->working_directory ??= '/home/ploi';
            $chat->ai_provider_id ??= AiProvider::getDefault()?->id;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GeneralChatMessage::class);
    }

    public function isRunning(): bool
    {
        return $this->status === GeneralChatStatus::Running;
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => GeneralChatStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => GeneralChatStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => GeneralChatStatus::Failed,
            'completed_at' => now(),
        ]);
    }

    public function markAsViewed(): void
    {
        $this->update(['last_viewed_at' => now()]);
    }

    public function hasUnreadReply(): bool
    {
        // No last_viewed_at means never viewed - check if there are any assistant messages
        if (! $this->last_viewed_at) {
            return $this->messages()
                ->where('role', \App\Enums\MessageRole::Assistant)
                ->exists();
        }

        // Check if there's an assistant message after last viewed and chat is not running
        return ! $this->isRunning() && $this->messages()
            ->where('role', \App\Enums\MessageRole::Assistant)
            ->where('created_at', '>', $this->last_viewed_at)
            ->exists();
    }
}
