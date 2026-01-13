<?php

namespace App\Models;

use App\Enums\QuotaPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'base_url',
        'api_key',
        'model',
        'context_window',
        'is_active',
        'is_default',
        'quota_limit',
        'quota_period',
        'quota_used',
        'quota_resets_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'quota_limit' => 'integer',
            'quota_used' => 'integer',
            'quota_resets_at' => 'datetime',
            'quota_period' => QuotaPeriod::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getEnvironmentVariables(): array
    {
        if ($this->name === 'claude') {
            return [];
        }

        return array_filter([
            'ANTHROPIC_BASE_URL' => $this->base_url,
            'ANTHROPIC_API_KEY' => $this->api_key,
            'ANTHROPIC_MODEL' => $this->model,
        ]);
    }

    public function isGlm(): bool
    {
        return $this->name === 'glm';
    }

    public function isMinimax(): bool
    {
        return $this->name === 'minimax';
    }

    public function isClaude(): bool
    {
        return $this->name === 'claude';
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('name', 'claude')->where('is_active', true)->first()
            ?? static::where('is_active', true)->first();
    }

    public function getQuotaPercentage(): float
    {
        if (! $this->quota_limit || $this->quota_limit === 0) {
            return 0;
        }

        return min(100, ($this->quota_used / $this->quota_limit) * 100);
    }

    public function incrementUsage(int $tokensIn, int $tokensOut): void
    {
        $this->increment('quota_used', $tokensIn + $tokensOut);
    }

    public function resetQuotaIfNeeded(): void
    {
        if (! $this->quota_resets_at || now()->greaterThan($this->quota_resets_at)) {
            $this->update([
                'quota_used' => 0,
                'quota_resets_at' => $this->calculateNextReset(),
            ]);
        }
    }

    public function calculateNextReset(): Carbon
    {
        return match ($this->quota_period) {
            QuotaPeriod::FiveHour => now()->addHours(5),
            default => now()->startOfMonth()->addMonth(),
        };
    }

    public function getContextWindow(): int
    {
        return $this->context_window ?? 200000;
    }
}
