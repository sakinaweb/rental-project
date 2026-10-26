<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\City;
use App\Models\Location;
use App\Models\PricingRule;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

// Demo fleet for previews (MD §7.13): php artisan db:seed --class=DemoSeeder. Idempotent; run after DatabaseSeeder.
// Photos are real, from Wikimedia Commons; authors and licences are in images/CREDITS.tsv.
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $city = City::where('name', 'Mumbai')->firstOrFail();
        $location = Location::firstOrCreate(['city_id' => $city->id, 'name' => 'Andheri West'], ['lat' => 19.1364, 'lng' => 72.8296, 'type' => 'branch']);
        $branch = Branch::firstOrCreate(['location_id' => $location->id], ['name' => 'Mumbai Andheri', 'contact' => '+91 22 4000 1000']);

        // [image slug, brand, model, category, type, seats, transmission, fuel, day rate in paise]
        $fleet = [
            ['re-classic-350', 'Royal Enfield', 'Classic 350', 'Cruiser', 'bike', 2, 'manual', 'petrol', 150000],
            ['honda-activa', 'Honda', 'Activa 6G', 'Scooter', 'bike', 2, 'automatic', 'petrol', 50000],
            ['bajaj-pulsar', 'Bajaj', 'Pulsar NS400Z', 'Sport', 'bike', 2, 'manual', 'petrol', 120000],
            ['ktm-duke-390', 'KTM', '390 Duke', 'Sport', 'bike', 2, 'manual', 'petrol', 200000],
            ['yamaha-r15', 'Yamaha', 'YZF-R15', 'Sport', 'bike', 2, 'manual', 'petrol', 150000],
            ['tvs-apache', 'TVS', 'Apache RTR 310', 'Sport', 'bike', 2, 'manual', 'petrol', 130000],
            ['hero-splendor', 'Hero', 'Splendor iSmart', 'Commuter', 'bike', 2, 'manual', 'petrol', 45000],
            ['maruti-swift', 'Maruti Suzuki', 'Swift', 'Hatchback', 'car', 5, 'manual', 'petrol', 200000],
            ['tata-nexon', 'Tata', 'Nexon', 'SUV', 'car', 5, 'automatic', 'diesel', 300000],
            ['hyundai-creta', 'Hyundai', 'Creta', 'SUV', 'car', 5, 'automatic', 'petrol', 350000],
            ['honda-city', 'Honda', 'City', 'Sedan', 'car', 5, 'automatic', 'petrol', 300000],
            ['toyota-innova', 'Toyota', 'Innova Hycross', 'MPV', 'car', 7, 'automatic', 'hybrid', 500000],
            ['mahindra-thar', 'Mahindra', 'Thar', 'SUV', 'car', 4, 'manual', 'diesel', 450000],
            ['toyota-fortuner', 'Toyota', 'Fortuner', 'SUV', 'car', 7, 'automatic', 'diesel', 700000],
        ];

        $disk = Storage::disk(VehicleModel::imageDisk());
        foreach ($fleet as $i => [$slug, $brand, $name, $category, $type, $seats, $transmission, $fuel, $rate]) {
            $file = glob(__DIR__."/images/$slug.*")[0] ?? null;
            $image = null;
            if ($file) {
                $image = 'vehicle-models/demo-'.basename($file);
                $disk->put($image, file_get_contents($file));
            }

            $model = VehicleModel::updateOrCreate(
                ['brand_id' => Brand::firstOrCreate(['name' => $brand])->id, 'name' => $name],
                [
                    'category_id' => VehicleCategory::firstOrCreate(['name' => $category, 'type' => $type])->id,
                    'seats' => $seats, 'transmission' => $transmission, 'fuel_type' => $fuel, 'image_path' => $image,
                ],
            );
            PricingRule::firstOrCreate(['scope_type' => 'model', 'scope_id' => $model->id, 'unit' => 'day'], ['base_rate' => $rate]);

            foreach ([1, 2] as $n) {
                Vehicle::firstOrCreate(
                    ['plate_no' => sprintf('MH-02-%s-%04d', $type === 'bike' ? 'BK' : 'CR', ($i + 1) * 10 + $n)],
                    ['model_id' => $model->id, 'branch_id' => $branch->id, 'owner_type' => 'company', 'status' => Vehicle::STATUS_AVAILABLE, 'odometer' => 1000 * $n],
                );
            }
        }
    }
}
