<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\VehicleCategory;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VehicleModel> */
class VehicleModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->word(),
            'category_id' => VehicleCategory::factory(),
            'seats' => 5,
            'transmission' => 'manual',
            'fuel_type' => 'petrol',
        ];
    }
}
