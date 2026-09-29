<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'model_id' => VehicleModel::factory(),
            'branch_id' => Branch::factory(),
            'owner_type' => 'company',
            'plate_no' => strtoupper(fake()->unique()->bothify('??##??####')),
            'status' => Vehicle::STATUS_AVAILABLE,
            'odometer' => fake()->numberBetween(0, 50000),
        ];
    }
}
