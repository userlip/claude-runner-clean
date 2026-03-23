<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ConnectionType;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'push_notifications_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'push_notifications_enabled' => 'boolean',
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? url(Storage::url($this->avatar_url)) : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function githubConnection(): HasOne
    {
        return $this->hasOne(Connection::class)->where('type', ConnectionType::GitHub)->where('is_active', true);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    public function snippets(): HasMany
    {
        return $this->hasMany(Snippet::class)->orderBy('sort_order');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function googleAnalyticsConnections(): HasMany
    {
        return $this->hasMany(Connection::class)->where('type', ConnectionType::GoogleAnalytics);
    }

    public function searchConsoleConnections(): HasMany
    {
        return $this->hasMany(Connection::class)->where('type', ConnectionType::SearchConsole);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    public function connectionOfType(ConnectionType $type): ?Connection
    {
        return $this->connections()->where('type', $type)->where('is_active', true)->first();
    }

    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Get a user setting value by key.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        // Attempt JSON decode for structured values
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    /**
     * Set a user setting value by key.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $storedValue = is_string($value) ? $value : json_encode($value);

        $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue],
        );
    }
}
