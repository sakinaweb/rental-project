<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Cities are reference data from DatabaseSeeder; defaults to the first one (Mumbai, IN).
            'city_id' => fn () => City::query()->orderBy('id')->value('id'),
            'name' => fake()->streetName(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'type' => 'branch',
        ];
    }
}
