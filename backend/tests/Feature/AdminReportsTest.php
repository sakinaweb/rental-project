<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exports\RevenueExport;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Damage;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingInvoice;
use App\Services\DashboardStats;
use App\Services\RevenueReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function completed(string $completedAt, int $grand, int $tax, string $currency = 'INR', ?Vehicle $vehicle = null, string $status = 'completed'): Booking
    {
        $booking = Booking::factory()->create([
            'vehicle_id' => ($vehicle ?? Vehicle::factory()->create())->id, 'status' => $status, 'currency_code' => $currency,
            'subtotal' => $grand - $tax + 100, 'discount_total' => 100, 'tax_total' => $tax, 'grand_total' => $grand,
        ]);
        $booking->forceFill(['completed_at' => $completedAt])->save();

        return $booking;
    }

    private function dubaiVehicle(): Vehicle
    {
        $location = Location::factory()->create(['city_id' => City::where('name', 'Dubai')->value('id')]);

        return Vehicle::factory()->create(['branch_id' => Branch::factory()->create(['location_id' => $location->id])->id]);
    }

    public function test_revenue_is_earned_completed_and_settled_bookings_per_period_and_currency(): void
    {
        $this->completed('2026-10-01 10:00', 11800, 1800);
        $this->completed('2026-10-01 18:00', 5900, 900, status: 'settled');
        $this->completed('2026-10-02 09:00', 1050, 50, 'AED', $this->dubaiVehicle());
        $this->completed('2026-09-30 23:59', 999999, 0);                       // outside the period
        Booking::factory()->create(['status' => 'confirmed', 'grand_total' => 777777]); // not earned yet

        $rows = app(RevenueReport::class)->rows(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31 23:59:59'));

        $this->assertSame([
            ['period' => '2026-10-01', 'currency_code' => 'INR', 'bookings' => 2, 'subtotal' => 15200, 'discount_total' => 200, 'tax_total' => 2700, 'grand_total' => 17700, 'net_revenue' => 15000],
            ['period' => '2026-10-02', 'currency_code' => 'AED', 'bookings' => 1, 'subtotal' => 1100, 'discount_total' => 100, 'tax_total' => 50, 'grand_total' => 1050, 'net_revenue' => 1000],
        ], $rows->all());

        $monthly = app(RevenueReport::class)->rows(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31 23:59:59'), 'month');
        $this->assertSame(['2026-10|AED', '2026-10|INR'], $monthly->map(fn ($r) => $r['period'].'|'.$r['currency_code'])->all());

        $ae = Country::where('iso_code', 'AE')->value('id');
        $this->assertSame(['AED'], app(RevenueReport::class)->rows(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), 'day', $ae)->pluck('currency_code')->all());
    }

    public function test_revenue_api_is_admin_only_paginated_and_validated(): void
    {
        $this->completed('2026-10-01 10:00', 11800, 1800);
        $url = '/api/v1/admin/reports/revenue?from=2026-10-01&to=2026-10-31';

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertForbidden();

        $this->actingAs(User::factory()->role(Role::ADMIN)->create(), 'sanctum');
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.0.net_revenue', 10000)
            ->assertJsonPath('data.0.currency_code', 'INR')
            ->assertJsonPath('total', 1);
        $this->getJson('/api/v1/admin/reports/revenue?from=2026-10-31&to=2026-10-01&group_by=year')
            ->assertUnprocessable()->assertJsonValidationErrors(['to', 'group_by']);
    }

    public function test_revenue_csv_export(): void
    {
        $this->completed('2026-10-01 10:00', 11800, 1800);
        $rows = app(RevenueReport::class)->rows(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $csv = Excel::raw(new RevenueExport($rows), \Maatwebsite\Excel\Excel::CSV);

        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertSame(RevenueReport::COLUMNS, $lines[0]);
        $this->assertSame(['2026-10-01', 'INR', '1', '10100', '100', '1800', '11800', '10000'], $lines[1]);
    }

    public function test_dashboard_numbers(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        Booking::factory()->create(['status' => BookingStatus::PickedUp, 'start_at' => '2026-10-14 10:00', 'end_at' => '2026-10-15 18:00']);
        Booking::factory()->create(['status' => BookingStatus::Confirmed, 'start_at' => '2026-10-15 16:00', 'end_at' => '2026-10-16 16:00']);
        Booking::factory()->create(['status' => BookingStatus::PendingPayment]);
        $late = Booking::factory()->create(['status' => BookingStatus::PickedUp, 'start_at' => '2026-10-13 10:00', 'end_at' => '2026-10-15 09:00']);
        Damage::create(['booking_id' => $late->id, 'description' => 'Scratch', 'cost' => 500, 'status' => Damage::REPORTED]);
        Vehicle::factory()->create(['status' => 'maintenance']);
        $this->completed('2026-10-02 10:00', 11800, 1800);

        $stats = app(DashboardStats::class)->summary();

        $this->assertSame(2, $stats['active_rentals']);
        $this->assertSame(1, $stats['pickups_today']);
        $this->assertSame(2, $stats['returns_today']);
        $this->assertSame(1, $stats['overdue_returns']); // due 09:00, now 12:00
        $this->assertSame(1, $stats['damages_to_review']);
        $this->assertSame(1, $stats['awaiting_payment']);
        $this->assertSame(['INR' => 10000], $stats['revenue_this_month']);
        $this->assertSame(1, $stats['in_maintenance']);
        $this->assertSame(40, $stats['utilization_pct']); // 2 of 5 in-service vehicles on rent
    }

    public function test_invoice_pdf(): void
    {
        $booking = $this->completed('2026-10-01 10:00', 11800, 1800);

        $pdf = app(BookingInvoice::class)->pdf($booking);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame("invoice-booking-{$booking->id}.pdf", app(BookingInvoice::class)->filename($booking));
        $this->assertStringContainsString('Booking #'.$booking->id, view('invoices.booking', ['booking' => $booking->fresh()])->render());
    }

    public function test_assign_moves_booking_to_available_vehicle_in_same_country_and_is_audited(): void
    {
        $admin = User::factory()->role(Role::ADMIN)->create();
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'start_at' => '2026-11-02 10:00', 'end_at' => '2026-11-03 10:00']);
        $free = Vehicle::factory()->create();
        $busy = Vehicle::factory()->create();
        Booking::factory()->create(['vehicle_id' => $busy->id, 'status' => BookingStatus::Confirmed, 'start_at' => '2026-11-02 12:00', 'end_at' => '2026-11-02 20:00']);
        $url = "/api/v1/admin/bookings/{$booking->id}/assign";

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson($url, ['vehicle_id' => $free->id])->assertForbidden();

        $this->actingAs($admin, 'sanctum');
        $this->postJson($url, ['vehicle_id' => $busy->id])->assertUnprocessable()->assertJsonPath('message', __('messages.vehicle_unavailable'));
        $this->postJson($url, ['vehicle_id' => $this->dubaiVehicle()->id])->assertUnprocessable(); // other country / currency

        $old = $booking->vehicle_id;
        $this->postJson($url, ['vehicle_id' => $free->id])->assertOk()->assertJsonPath('data.vehicle.id', $free->id)->assertJsonPath('data.grand_total', $booking->fresh()->grand_total);

        $log = AuditLog::sole();
        $this->assertSame(['booking.assign', $admin->id, 'Booking', $booking->id], [$log->action, $log->actor_id, $log->subject_type, $log->subject_id]);
        $this->assertEquals(['from_vehicle_id' => $old, 'to_vehicle_id' => $free->id], $log->meta_json);

        $booking->fresh()->transitionTo(BookingStatus::PickedUp);
        $this->postJson($url, ['vehicle_id' => $old])->assertUnprocessable()->assertJsonPath('message', __('messages.booking_invalid_status'));
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('app:create-admin', ['email' => 'boss@example.com', '--name' => 'Boss', '--password' => 'short'])->assertFailed();
        $this->artisan('app:create-admin', ['email' => 'boss@example.com', '--name' => 'Boss', '--password' => 'long-enough-pw'])->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->sole();
        $this->assertTrue($user->hasRole(Role::ADMIN));
        $this->assertTrue(Hash::check('long-enough-pw', $user->password));

        $customer = User::factory()->create(['email' => 'promote@example.com']);
        $this->artisan('app:create-admin', ['email' => 'promote@example.com', '--password' => 'long-enough-pw'])->assertSuccessful();
        $this->assertTrue($customer->fresh()->hasRole(Role::ADMIN));
        $this->assertSame($customer->name, $customer->fresh()->name);
    }
}
