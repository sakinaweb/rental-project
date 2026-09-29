<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

// Reference data every install needs. Idempotent: safe to re-run. Demo data comes in Phase 13.
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (Role::ALL as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $countries = [
            ['IN', 'India', 'INR', ['Mumbai', 'Bengaluru', 'New Delhi']],
            ['AE', 'United Arab Emirates', 'AED', ['Dubai', 'Abu Dhabi']],
            ['US', 'United States', 'USD', ['New York', 'Los Angeles']],
            ['DE', 'Germany', 'EUR', ['Berlin', 'Munich']],
        ];

        foreach ($countries as [$iso, $name, $currency, $cities]) {
            $country = Country::firstOrCreate(['iso_code' => $iso], ['name' => $name, 'currency_code' => $currency]);
            $country->pack()->firstOrCreate([]);
            foreach ($cities as $city) {
                $country->cities()->firstOrCreate(['name' => $city]);
            }
        }

        // Global feature flags (MD §9): editable from admin, off by default.
        foreach (['features.partner', 'features.investor'] as $flag) {
            Setting::firstOrCreate(['key' => $flag], ['value_json' => false]);
        }
    }
}
