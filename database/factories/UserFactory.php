<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\UnitKerja;
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => Role::firstOrCreate(['nama' => 'staf'])->id,
            'unit_id' => UnitKerja::firstOrCreate(['kode' => 'TU'], ['nama' => 'Tata Usaha'])->id,
            'must_change_password' => false,
            'aktif' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(string $nama): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['nama' => $nama])->id,
        ]);
    }

    public function admin(): static
    {
        return $this->role('admin');
    }

    public function staf(): static
    {
        return $this->role('staf');
    }

    public function unit(?UnitKerja $unit): static
    {
        return $this->state(fn () => ['unit_id' => $unit?->id]);
    }
}
