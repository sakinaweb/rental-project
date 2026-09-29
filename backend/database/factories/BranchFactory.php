<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return ['location_id' => Location::factory(), 'name' => fake()->company(), 'contact' => fake()->phoneNumber()];
    }
}
