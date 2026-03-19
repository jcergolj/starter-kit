<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    /** @use HasFactory<\Database\Factories\InvitationFactory> */
    use HasFactory;

    protected $guarded = [];

    public static function createFor(string $email, RoleEnum $role = RoleEnum::User, string $lang = 'en'): self
    {
        return self::create([
            'email' => $email,
            'role' => $role,
            'lang' => $lang,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at) && now()->lt($this->expires_at);
    }

    public function accept(): void
    {
        $this->update(['accepted_at' => now()]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    protected function casts(): array
    {
        return [
            'role' => RoleEnum::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
