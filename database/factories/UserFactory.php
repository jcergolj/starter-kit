<?php

namespace Database\Factories;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'settings' => ['lang' => 'en'],
            'role' => RoleEnum::User,
            'blocked_at' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => RoleEnum::Admin,
        ]);
    }

    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => RoleEnum::Superadmin,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => now(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactorAuthenticationEnabled(): static
    {
        $secretLength = (int) config('fortify-options.two-factor-authentication.secret-length', 16);

        return $this->state([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(resolve(TwoFactorAuthenticationProvider::class)->generateSecretKey($secretLength)),
            'two_factor_recovery_codes' => encrypt(json_encode([
                'recovery-code-1',
                'recovery-code-2',
                'recovery-code-3',
                'recovery-code-4',
                'recovery-code-5',
                'recovery-code-6',
                'recovery-code-7',
                'recovery-code-8',
            ])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
