<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'avatar', 'settings', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, HasAvatar
{
    use HasUuids, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user->hasAdvancedMode()) {
                $user->setSetting('accounts_receivable', false);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    public function hasSetting(string $key): bool
    {
        return (bool) ($this->settings[$key] ?? false);
    }

    public function hasAdvancedMode(): bool
    {
        return $this->hasSetting('advanced');
    }

    public function isTither(): bool
    {
        return $this->hasSetting('tither');
    }

    public function usesAccountsReceivable(): bool
    {
        return $this->hasAdvancedMode() && $this->hasSetting('accounts_receivable');
    }

    public function setSetting(string $key, bool $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar
        ? preg_replace('/=s\d+-c$/', '=s40-c', $this->avatar)
        : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function providers(): HasMany
    {
        return $this->hasMany(UserProvider::class);
    }
}
