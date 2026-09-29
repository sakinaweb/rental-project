<?php

namespace Database\Factories;

use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VehicleCategory> */
class VehicleCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'type' => 'car'];
    }
}
