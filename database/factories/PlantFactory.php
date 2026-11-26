<?php

namespace Database\Factories;

use App\Models\Plant;
use App\Models\PlantCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plant> */
class PlantFactory extends Factory
{
    protected $model = Plant::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => PlantCategory::factory(),
            'name' => fake()->word().' plant',
            'type' => fake()->randomElement(['شجرة', 'شجيرة', 'عشبة']),
            'planting_date' => fake()->date(),
            'latitude' => fake()->latitude(29, 37),
            'longitude' => fake()->longitude(39, 48),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }
}
