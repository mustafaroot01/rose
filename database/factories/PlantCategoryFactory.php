<?php

namespace Database\Factories;

use App\Models\PlantCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlantCategory> */
class PlantCategoryFactory extends Factory
{
    protected $model = PlantCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'icon' => 'tabler-leaf',
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
