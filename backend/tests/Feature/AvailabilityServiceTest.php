<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // buffer_minutes = 60

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vehicle = Vehicle::factory()->create();
        // Existing booking: 10:00-18:00 on 2026-10-05
        Booking::factory()->create(['vehicle_id' => $this->vehicle->id, 'start_at' => '2026-10-05 10:00', 'end_at' => '2026-10-05 18:00']);
    }

    private function available(string $from, string $to, ?int $vehicleId = null): bool
    {
        return app(AvailabilityService::class)->isAvailable($vehicleId ?? $this->vehicle->id, Carbon::parse($from), Carbon::parse($to));
    }

    public function test_overlap_blocks(): void
    {
        $this->assertFalse($this->available('2026-10-05 12:00', '2026-10-05 14:00'));
        $this->assertFalse($this->available('2026-10-05 08:00', '2026-10-05 11:00'));
        $this->assertFalse($this->available('2026-10-04 00:00', '2026-10-07 00:00'));
    }

    public function test_buffer_keeps_turnaround_time_free(): void
    {
        $this->assertFalse($this->available('2026-10-05 18:30', '2026-10-05 20:00')); // 30 min after end
        $this->assertTrue($this->available('2026-10-05 19:00', '2026-10-05 20:00'));  // exactly 60 min after
        $this->assertFalse($this->available('2026-10-05 08:00', '2026-10-05 09:30')); // 30 min before start
        $this->assertTrue($this->available('2026-10-05 08:00', '2026-10-05 09:00'));

        Setting::updateOrCreate(['key' => 'availability.buffer_minutes'], ['value_json' => 0]);
        $this->assertTrue($this->available('2026-10-05 18:00', '2026-10-05 20:00'));
    }

    public function test_only_blocking_statuses_hold_the_vehicle(): void
    {
        $booking = Booking::first();

        foreach (['cancelled', 'no_show', 'returned', 'completed', 'settled'] as $status) {
            $booking->update(['status' => $status]);
            $this->assertTrue($this->available('2026-10-05 12:00', '2026-10-05 14:00'), $status);
        }
        foreach (Booking::BLOCKING_STATUSES as $status) {
            $booking->update(['status' => $status]);
            $this->assertFalse($this->available('2026-10-05 12:00', '2026-10-05 14:00'), $status);
        }

        $booking->delete();
        $this->assertTrue($this->available('2026-10-05 12:00', '2026-10-05 14:00'), 'soft-deleted');
    }

    public function test_out_of_service_or_missing_vehicle_is_never_available(): void
    {
        $other = Vehicle::factory()->create(['status' => 'maintenance']);
        $this->assertFalse($this->available('2026-11-01 10:00', '2026-11-02 10:00', $other->id));

        $other->update(['status' => Vehicle::STATUS_AVAILABLE]);
        $this->assertTrue($this->available('2026-11-01 10:00', '2026-11-02 10:00', $other->id));

        $other->delete();
        $this->assertFalse($this->available('2026-11-01 10:00', '2026-11-02 10:00', $other->id));
        $this->assertFalse($this->available('2026-11-01 10:00', '2026-11-02 10:00', 999999));
    }

    public function test_check_takes_a_row_lock_on_the_vehicle(): void
    {
        DB::enableQueryLog();
        $this->available('2026-11-01 10:00', '2026-11-02 10:00');

        $this->assertStringContainsString('for update', DB::getQueryLog()[0]['query']);
        $this->assertStringContainsString('`vehicles`', DB::getQueryLog()[0]['query']);
    }
}
