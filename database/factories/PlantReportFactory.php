<?php

namespace Database\Factories;

use App\Models\Plant;
use App\Models\PlantReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlantReport> */
class PlantReportFactory extends Factory
{
    protected $model = PlantReport::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'reporter_id' => User::factory(),
            'reason' => fake()->randomElement(['withered', 'dead', 'needs_water', 'vandalism', 'wrong_location', 'other']),
            'reason_note' => fake()->optional()->sentence(),
            'status' => 'new',
        ];
    }
}
