<?php

namespace Tests\Feature;

use App\Enums\BookingStatus as S;
use App\Models\Booking;
use App\Models\Damage;
use App\Models\Inspection;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Phase 10 staff app API: branch scope, handover and return checklists with photos, damages reported for the admin.
class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Vehicle $vehicle;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');
        Storage::fake(Inspection::disk());
        $this->vehicle = Vehicle::factory()->create(['odometer' => 12000]);
        $this->staff = User::factory()->role(Role::STAFF)->create(['branch_id' => $this->vehicle->branch_id]);
        $this->actingAs($this->staff, 'sanctum');
    }

    private function booking(S $status, string $start = '2026-10-10 10:00', string $end = '2026-10-12 10:00', ?Vehicle $vehicle = null): Booking
    {
        return Booking::factory()->create(['vehicle_id' => ($vehicle ?? $this->vehicle)->id, 'status' => $status, 'start_at' => $start, 'end_at' => $end]);
    }

    private function checklist(int $odometer, int $photos = 4): array
    {
        return [
            'odometer' => $odometer, 'fuel_level' => 75, 'notes' => 'Clean',
            'photos' => array_map(fn ($side) => UploadedFile::fake()->image("$side.jpg"), array_slice(['front', 'back', 'left', 'right', 'dash'], 0, $photos)),
        ];
    }

    public function test_only_staff_of_the_branch_reach_its_bookings(): void
    {
        $other = $this->booking(S::Confirmed, vehicle: Vehicle::factory()->create()); // another branch
        $this->getJson("/api/v1/staff/bookings/{$other->id}")->assertNotFound();

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/staff/bookings')->assertForbidden(); // customer
        $this->actingAs(User::factory()->role(Role::STAFF)->create(), 'sanctum')->getJson('/api/v1/staff/bookings')
            ->assertUnprocessable()->assertJsonPath('message', __('messages.staff_no_branch'));
    }

    public function test_day_list_shows_due_pickups_and_rentals_out(): void
    {
        $today = $this->booking(S::Confirmed);
        $late = $this->booking(S::Confirmed, '2026-10-09 10:00', '2026-10-09 18:00');
        $tomorrow = $this->booking(S::Confirmed, '2026-10-11 10:00', '2026-10-11 18:00');
        $out = $this->booking(S::PickedUp, '2026-10-08 10:00', '2026-10-15 10:00');
        $this->booking(S::Confirmed, vehicle: Vehicle::factory()->create()); // another branch

        $this->getJson('/api/v1/staff/bookings')->assertOk()
            ->assertJsonPath('data.pickups.*.id', [$late->id, $today->id])
            ->assertJsonPath('data.returns.*.id', [$out->id])
            ->assertJsonPath('data.pickups.1.vehicle.plate_no', $this->vehicle->plate_no);
        $this->getJson('/api/v1/staff/bookings?date=2026-10-11')->assertJsonPath('data.pickups.*.id', [$late->id, $today->id, $tomorrow->id]);
    }

    public function test_handover_needs_the_full_checklist_then_marks_picked_up(): void
    {
        $booking = $this->booking(S::Confirmed);

        $this->post("/api/v1/staff/bookings/{$booking->id}/pickup", $this->checklist(12010, photos: 3), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->post("/api/v1/staff/bookings/{$booking->id}/pickup", ['odometer' => -1, 'fuel_level' => 101] + $this->checklist(0), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['odometer', 'fuel_level']);

        $this->post("/api/v1/staff/bookings/{$booking->id}/pickup", $this->checklist(12010), ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.status', 'picked_up')->assertJsonPath('data.inspections.0.stage', 'pickup')
            ->assertJsonPath('data.inspections.0.inspected_by', $this->staff->name)->assertJsonCount(4, 'data.inspections.0.photos');

        $inspection = Inspection::sole();
        Storage::disk(Inspection::disk())->assertExists($inspection->photos_json);
        $this->assertSame([12010, 75, $this->staff->id], [$inspection->odometer, $inspection->fuel_level, $inspection->inspected_by]);
        $this->assertSame(12010, $this->vehicle->fresh()->odometer);

        // A second handover is refused by the state machine, and its photos don't stay behind.
        $this->post("/api/v1/staff/bookings/{$booking->id}/pickup", $this->checklist(12010), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertCount(4, Storage::disk(Inspection::disk())->allFiles());
    }

    public function test_return_checks_the_odometer_and_reports_damages_for_the_admin(): void
    {
        $booking = $this->booking(S::Confirmed);
        $this->post("/api/v1/staff/bookings/{$booking->id}/pickup", $this->checklist(12010), ['Accept' => 'application/json'])->assertOk();

        $this->post("/api/v1/staff/bookings/{$booking->id}/return", $this->checklist(11999), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('message', __('messages.inspection_odometer_lower', ['km' => 12010]));

        $this->post("/api/v1/staff/bookings/{$booking->id}/return", $this->checklist(12250) + ['damages' => [
            ['description' => 'Scratch on rear door', 'cost' => 150000, 'photos' => [UploadedFile::fake()->image('scratch.jpg')]],
            ['description' => 'Mirror loose', 'cost' => 0],
        ]], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.status', 'returned')->assertJsonPath('data.inspections.1.stage', 'return')
            ->assertJsonPath('data.damages.0.status', Damage::REPORTED)->assertJsonCount(1, 'data.damages.0.photos');

        $damage = Damage::where('description', 'Scratch on rear door')->sole();
        Storage::disk(Inspection::disk())->assertExists($damage->evidence_json['photos']);
        $this->assertSame($this->staff->id, $damage->evidence_json['reported_by']);
        $this->assertCount(4 + 4 + 1, Storage::disk(Inspection::disk())->allFiles());
        $this->assertSame(S::Returned, $booking->fresh()->status);
        $this->assertSame(12250, $this->vehicle->fresh()->odometer);
    }

    public function test_admin_booking_page_shows_the_checklists(): void
    {
        $booking = $this->booking(S::Confirmed);
        $this->post("/api/v1/staff/bookings/{$booking->id}/pickup", $this->checklist(12010), ['Accept' => 'application/json'])->assertOk();

        $this->actingAs(User::factory()->role(Role::ADMIN)->create(), 'web')->get("/admin/bookings/{$booking->id}")->assertOk()
            ->assertSee('Handover and return')->assertSee('12,010')->assertSee('Clean');
    }
}
