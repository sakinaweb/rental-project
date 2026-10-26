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

        $this->assertSame(14, VehicleModel::count());
        $this->assertSame(28, Vehicle::listed()->count());
        $this->assertGreaterThanOrEqual(12, VehicleModel::whereNotNull('image_path')->count());
        Storage::disk(VehicleModel::imageDisk())->assertExists('vehicle-models/demo-honda-city.jpg');
    }
}
