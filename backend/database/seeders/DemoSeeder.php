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
            ['suzuki-access', 'Suzuki', 'Access 125', 'Scooter', 'bike', 2, 'automatic', 'petrol', 55000],
            ['re-himalayan', 'Royal Enfield', 'Himalayan 450', 'Premium', 'bike', 2, 'manual', 'petrol', 250000],
            ['triumph-speed-400', 'Triumph', 'Speed 400', 'Premium', 'bike', 2, 'manual', 'petrol', 280000],
            ['hyundai-i20', 'Hyundai', 'i20', 'Hatchback', 'car', 5, 'manual', 'petrol', 220000],
            ['skoda-slavia', 'Škoda', 'Slavia', 'Sedan', 'car', 5, 'automatic', 'petrol', 320000],
            ['kia-carens', 'Kia', 'Carens', 'MPV', 'car', 7, 'manual', 'diesel', 400000],
            ['bmw-3-series', 'BMW', '3 Series', 'Luxury', 'car', 5, 'automatic', 'petrol', 900000],
            ['mercedes-e-class', 'Mercedes-Benz', 'E-Class', 'Luxury', 'car', 5, 'automatic', 'diesel', 1200000],
        ];

        // Engine size in cc, from each maker's published specifications.
        $cc = [
            're-classic-350' => 349, 'honda-activa' => 110, 'bajaj-pulsar' => 373, 'ktm-duke-390' => 399, 'yamaha-r15' => 155,
            'tvs-apache' => 312, 'hero-splendor' => 113, 'maruti-swift' => 1197, 'tata-nexon' => 1497, 'hyundai-creta' => 1497,
            'honda-city' => 1498, 'toyota-innova' => 1987, 'mahindra-thar' => 2184, 'toyota-fortuner' => 2755, 'suzuki-access' => 124,
            're-himalayan' => 452, 'triumph-speed-400' => 398, 'hyundai-i20' => 1197, 'skoda-slavia' => 1498, 'kia-carens' => 1493,
            'bmw-3-series' => 1998, 'mercedes-e-class' => 1993,
        ];

        $disk = Storage::disk(VehicleModel::imageDisk());
        foreach ($fleet as $i => [$slug, $brand, $name, $category, $type, $seats, $transmission, $fuel, $rate]) {
            $file = glob(__DIR__."/images/$slug.*")[0] ?? null;
            $image = null;
            if ($file) {
                $image = "vehicle-models/demo-$slug.jpg";
                $disk->put($image, self::cardPhoto($file));
            }

            $model = VehicleModel::updateOrCreate(
                ['brand_id' => Brand::firstOrCreate(['name' => $brand])->id, 'name' => $name],
                [
                    'category_id' => VehicleCategory::firstOrCreate(['name' => $category, 'type' => $type])->id,
                    'seats' => $seats, 'transmission' => $transmission, 'fuel_type' => $fuel, 'engine_cc' => $cc[$slug], 'image_path' => $image,
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

    /**
     * 1200×750 progressive JPEG (~100–200 KB) so every card has the same shape and loads fast. Photos close to 16:10
     * are cropped to fill; very wide or tall ones (e.g. a portrait scooter) are fitted whole over a blurred copy of
     * themselves. Without GD the original file is used as is.
     */
    private static function cardPhoto(string $file): string
    {
        $src = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($file)) : false;
        if (! $src) {
            return (string) file_get_contents($file);
        }

        [$w, $h, $sw, $sh] = [1200, 750, imagesx($src), imagesy($src)];
        $out = imagecreatetruecolor($w, $h);
        imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
        $cover = function () use ($out, $src, $w, $h, $sw, $sh) {
            $scale = max($w / $sw, $h / $sh);
            $cw = (int) round($w / $scale);
            $ch = (int) round($h / $scale);
            imagecopyresampled($out, $src, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $w, $h, $cw, $ch);
        };

        $ratio = $sw / $sh;
        if ($ratio >= 1.3 && $ratio <= 1.9) {
            $cover();
        } else {
            // Blurred backdrop: blur a small copy of the cover crop, then scale it back up smoothly.
            $cover();
            $small = imagescale($out, 120, 75, IMG_BICUBIC);
            for ($n = 0; $n < 8; $n++) {
                imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
            }
            imagecopyresampled($out, $small, 0, 0, 0, 0, $w, $h, 120, 75);
            $scale = min($w / $sw, $h / $sh);
            $fw = (int) round($sw * $scale);
            $fh = (int) round($sh * $scale);
            imagecopyresampled($out, $src, intdiv($w - $fw, 2), intdiv($h - $fh, 2), 0, 0, $fw, $fh, $sw, $sh);
        }

        imageinterlace($out, true);
        ob_start();
        imagejpeg($out, null, 80);

        return (string) ob_get_clean();
    }
}
