<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Location;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function vehicleIn(string $city, array $model = [], array $attrs = []): Vehicle
    {
        $location = Location::factory()->create(['city_id' => City::where('name', $city)->value('id')]);

        return Vehicle::factory()->create($attrs + [
            'branch_id' => Branch::factory()->create(['location_id' => $location->id])->id,
            'model_id' => VehicleModel::factory()->create($model)->id,
        ]);
    }

    /** @return list<int> */
    private function ids(string $query = ''): array
    {
        return collect($this->getJson('/api/v1/vehicles'.$query)->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_search_returns_paginated_vehicles_with_details(): void
    {
        $this->vehicleIn('Mumbai');

        $this->getJson('/api/v1/vehicles')->assertOk()->assertJsonStructure([
            'data' => [['id', 'plate_no', 'status', 'model' => ['name', 'seats', 'transmission', 'fuel_type'], 'brand' => ['name'],
                'category' => ['id', 'name', 'type'], 'branch' => ['name', 'location' => ['city_id', 'lat', 'lng']]]],
            'links', 'meta' => ['current_page', 'total'],
        ]);
    }

    public function test_search_filters(): void
    {
        $bike = VehicleCategory::factory()->create(['type' => 'bike']);
        $mumbaiCar = $this->vehicleIn('Mumbai', ['seats' => 5, 'transmission' => 'automatic', 'fuel_type' => 'electric']);
        $mumbaiBike = $this->vehicleIn('Mumbai', ['seats' => 2, 'category_id' => $bike->id]);
        $dubaiCar = $this->vehicleIn('Dubai', ['seats' => 7]);
        $mumbai = City::where('name', 'Mumbai')->value('id');

        $this->assertEqualsCanonicalizing([$mumbaiCar->id, $mumbaiBike->id], $this->ids("?city_id=$mumbai"));
        $this->assertSame([$mumbaiBike->id], $this->ids("?category={$bike->id}"));
        $this->assertSame([$mumbaiBike->id], $this->ids('?type=bike'));
        $this->assertSame([$mumbaiCar->id], $this->ids('?transmission=automatic'));
        $this->assertSame([$mumbaiCar->id], $this->ids('?fuel_type=electric'));
        $this->assertEqualsCanonicalizing([$mumbaiCar->id, $dubaiCar->id], $this->ids('?seats=5'));
        $this->assertSame([$dubaiCar->id], $this->ids('?brand_id='.$dubaiCar->vehicleModel->brand_id));
    }

    public function test_search_hides_out_of_service_deleted_and_inactive_country_vehicles(): void
    {
        $visible = $this->vehicleIn('Mumbai');
        $this->vehicleIn('Mumbai', [], ['status' => 'maintenance']);
        $this->vehicleIn('Mumbai')->delete();
        $this->vehicleIn('Berlin');
        Country::where('iso_code', 'DE')->update(['is_active' => false]);

        $this->assertSame([$visible->id], $this->ids());
    }

    public function test_period_search_excludes_booked_vehicles_including_buffer(): void
    {
        $free = $this->vehicleIn('Mumbai');
        $booked = $this->vehicleIn('Mumbai');
        $cancelled = $this->vehicleIn('Mumbai');
        Booking::factory()->create(['vehicle_id' => $booked->id, 'start_at' => '2026-10-05 10:00', 'end_at' => '2026-10-05 18:00']);
        Booking::factory()->create(['vehicle_id' => $cancelled->id, 'start_at' => '2026-10-05 10:00', 'end_at' => '2026-10-05 18:00', 'status' => 'cancelled']);

        $this->assertEqualsCanonicalizing([$free->id, $cancelled->id], $this->ids('?from=2026-10-05T18:30:00Z&to=2026-10-05T20:00:00Z'));
        $this->assertCount(3, $this->ids('?from=2026-10-05T19:00:00Z&to=2026-10-05T20:00:00Z'));
        // Offsets are converted: 23:30 in Dubai (+04:00) is 19:30 UTC, after the buffer.
        $this->assertCount(3, $this->ids('?from='.urlencode('2026-10-05T23:30:00+04:00').'&to='.urlencode('2026-10-06T01:00:00+04:00')));
    }

    public function test_search_validates_period(): void
    {
        $this->getJson('/api/v1/vehicles?from=2026-10-05T10:00:00Z')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/vehicles?from=2026-10-05T10:00:00Z&to=2026-10-05T09:00:00Z')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/vehicles?type=boat')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_show_returns_listed_vehicle_only(): void
    {
        $vehicle = $this->vehicleIn('Mumbai');
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertOk()->assertJsonPath('data.id', $vehicle->id);

        $vehicle->update(['status' => 'retired']);
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertNotFound();
    }

    public function test_model_photo_is_served_to_the_api_and_website_with_a_drawn_fallback(): void
    {
        $vehicle = $this->vehicleIn('Mumbai');
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertJsonPath('data.model.image_url', null);
        $this->get('/')->assertDontSee('vehicle-models/')->assertSee('<svg viewBox="0 0 220 120"', false); // drawn car

        $vehicle->vehicleModel->update(['image_path' => 'vehicle-models/swift.jpg']);
        $url = Storage::disk(VehicleModel::imageDisk())->url('vehicle-models/swift.jpg');
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertJsonPath('data.model.image_url', $url);
        $this->get('/')->assertSee($url);

        // Gallery and engine size: API lists the main photo first; the vehicle page shows every photo and the cc.
        $vehicle->vehicleModel->update(['gallery' => ['vehicle-models/swift-2.jpg'], 'engine_cc' => 1197]);
        $second = Storage::disk(VehicleModel::imageDisk())->url('vehicle-models/swift-2.jpg');
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertJsonPath('data.model.photo_urls', [$url, $second])->assertJsonPath('data.model.engine_cc', 1197);
        $this->get("/vehicles/{$vehicle->id}")->assertSee($second)->assertSee('1197 cc');
    }

    public function test_availability_endpoint(): void
    {
        $vehicle = $this->vehicleIn('Mumbai');
        Booking::factory()->create(['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-05 10:00', 'end_at' => '2026-10-05 18:00']);
        $url = "/api/v1/vehicles/{$vehicle->id}/availability";

        $this->getJson("$url?from=2026-10-05T12:00:00Z&to=2026-10-05T13:00:00Z")->assertOk()->assertJsonPath('data.available', false);
        $this->getJson("$url?from=2026-10-06T12:00:00Z&to=2026-10-06T13:00:00Z")->assertOk()
            ->assertJsonPath('data.available', true)->assertJsonPath('data.vehicle_id', $vehicle->id);
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson('/api/v1/vehicles/999999/availability?from=2026-10-06T12:00:00Z&to=2026-10-06T13:00:00Z')->assertNotFound();
    }

    public function test_categories_are_listed_paginated(): void
    {
        VehicleCategory::factory()->create(['name' => 'Scooter', 'type' => 'bike']);

        $this->getJson('/api/v1/vehicle-categories')->assertOk()
            ->assertJsonPath('data.0', fn ($c) => $c['name'] === 'Scooter' && $c['type'] === 'bike')
            ->assertJsonStructure(['meta' => ['total']]);
    }
}
