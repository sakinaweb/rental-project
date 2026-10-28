<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_seeds_a_bookable_demo_fleet_with_photos_and_is_idempotent(): void
    {
        Storage::fake(VehicleModel::imageDisk());
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(22, VehicleModel::count());
        $this->assertSame(44, Vehicle::listed()->count());
        $this->assertSame(22, VehicleModel::whereNotNull('image_path')->count());
        Storage::disk(VehicleModel::imageDisk())->assertExists('vehicle-models/demo-honda-city.jpg');
        // Every photo is stored as a 1200×750 JPEG, also the PNG sources.
        $creta = Storage::disk(VehicleModel::imageDisk())->get('vehicle-models/demo-hyundai-creta.jpg');
        $this->assertSame([1200, 750], array_slice((array) getimagesizefromstring((string) $creta), 0, 2));
    }
}
