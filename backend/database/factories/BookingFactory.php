<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $location = fn (array $attrs) => Vehicle::find($attrs['vehicle_id'])->branch->location_id;

        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'pickup_location_id' => $location,
            'dropoff_location_id' => $location,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'status' => 'confirmed',
            'currency_code' => 'INR',
        ];
    }
}
