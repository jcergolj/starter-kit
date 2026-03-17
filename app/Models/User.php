<?php

namespace App\Models;

use App\DataTransferObjects\UserSettings;
use App\Enums\RoleEnum;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::Admin || $this->role === RoleEnum::Superadmin;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === RoleEnum::Superadmin;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function initials(): string
    {
        return Str::of($this->username)->substr(0, 2)->upper();
    }

    protected function settings(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => UserSettings::fromArray(json_decode($value ?? '{}', true)),
            set: fn (UserSettings|array|null $value) => ['settings' => json_encode($value instanceof UserSettings ? $value->toArray() : ($value ?? ['lang' => 'en']))],
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleEnum::class,
            'blocked_at' => 'datetime',
        ];
    }
}
