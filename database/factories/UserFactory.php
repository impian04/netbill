<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => Role::SUPER_ADMIN,
            'pop_id'            => null,
            'phone'             => '628' . fake()->numerify('#########'),
            'is_active'         => true,
            'remember_token'    => Str::random(10),
        ];
    }

    public function admin(?Pop $pop = null): static
    {
        return $this->state(fn () => [
            'role'   => Role::ADMIN,
            'pop_id' => $pop?->id ?? Pop::factory(),
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role'   => Role::SUPER_ADMIN,
            'pop_id' => null,
        ]);
    }

    public function marketing(?Pop $pop = null): static
    {
        return $this->state(fn () => [
            'role'   => Role::MARKETING,
            'pop_id' => $pop?->id ?? Pop::factory(),
        ]);
    }

    public function surveyor(?Pop $pop = null): static
    {
        return $this->state(fn () => [
            'role'   => Role::SURVEYOR,
            'pop_id' => $pop?->id ?? Pop::factory(),
        ]);
    }

    public function technician(?Pop $pop = null): static
    {
        return $this->state(fn () => [
            'role'   => Role::TECHNICIAN,
            'pop_id' => $pop?->id ?? Pop::factory(),
        ]);
    }

    public function noc(): static
    {
        return $this->state(fn () => [
            'role'   => Role::NOC,
            'pop_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
