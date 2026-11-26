<?php

namespace Database\Factories;

use App\Models\Citizen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Citizen> */
class CitizenFactory extends Factory
{
    protected $model = Citizen::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('07#########'),
            'email' => fake()->unique()->safeEmail(),
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
        ];
    }

    public function blocked(): static
    {
        return $this->state(['status' => 'blocked']);
    }
}
