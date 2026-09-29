<?php

namespace Tests\Feature;

use App\Enums\BookingStatus as S;
use App\Models\Booking;
use App\Models\Damage;
use App\Models\Deposit;
use App\Services\BookingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // damage_hold_hours = 72

    public function test_state_machine_matches_md_section_6(): void
    {
        $allowed = [
            'draft' => ['pending_payment', 'cancelled'],
            'pending_payment' => ['confirmed', 'cancelled'],
            'confirmed' => ['picked_up', 'cancelled', 'no_show'],
            'picked_up' => ['returned'],
            'returned' => ['completed'],
            'completed' => ['settled'],
            'settled' => [], 'cancelled' => [], 'no_show' => [],
        ];

        foreach (S::cases() as $from) {
            foreach (S::cases() as $to) {
                $this->assertSame(in_array($to->value, $allowed[$from->value], true), $from->canTransitionTo($to), "{$from->value} -> {$to->value}");
            }
        }
    }

    public function test_illegal_transition_is_rejected_and_not_saved(): void
    {
        $booking = Booking::factory()->create(['status' => S::Confirmed]);

        try {
            $booking->transitionTo(S::Completed);
            $this->fail('expected illegal transition');
        } catch (DomainException) {
        }

        $this->assertSame(S::Confirmed, $booking->fresh()->status);
    }

    private function completedWithDeposit(): Booking
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $booking = Booking::factory()->create(['status' => S::Returned, 'deposit_amount' => 5000]);
        $booking->deposit()->create(['amount' => 5000, 'status' => Deposit::HELD]);
        $booking->transitionTo(S::Completed);

        return $booking;
    }

    public function test_completion_is_timestamped_and_settlement_waits_for_the_hold_window(): void
    {
        $booking = $this->completedWithDeposit();
        $this->assertEquals(Carbon::parse('2026-10-10 12:00:00'), $booking->completed_at);

        Carbon::setTestNow('2026-10-13 11:59:00');
        $this->expectExceptionMessage(__('messages.booking_hold_window'));
        app(BookingService::class)->settle($booking);
    }

    public function test_settlement_releases_deposit_minus_approved_damages(): void
    {
        $booking = $this->completedWithDeposit();
        $booking->damages()->create(['description' => 'Scratch', 'cost' => 1200, 'status' => Damage::APPROVED]);
        $booking->damages()->create(['description' => 'Dent', 'cost' => 800, 'status' => Damage::WAIVED]);
        $pending = $booking->damages()->create(['description' => 'Mirror', 'cost' => 999, 'status' => Damage::REPORTED]);

        Carbon::setTestNow('2026-10-13 12:00:00');
        try {
            app(BookingService::class)->settle($booking);
            $this->fail('expected refusal while a damage is under review');
        } catch (DomainException $e) {
            $this->assertSame(__('messages.booking_damage_pending'), $e->getMessage());
        }

        $pending->update(['status' => Damage::WAIVED]);
        $settled = app(BookingService::class)->settle($booking);

        $this->assertSame(S::Settled, $settled->status);
        $this->assertSame(Deposit::RELEASED, $settled->deposit->status);
        $this->assertSame(3800, $settled->deposit->refunded_amount);
    }

    public function test_approved_damages_above_the_deposit_refund_nothing(): void
    {
        $booking = $this->completedWithDeposit();
        $booking->damages()->create(['description' => 'Engine', 'cost' => 90000, 'status' => Damage::APPROVED]);

        Carbon::setTestNow('2026-10-14 00:00:00');
        $this->assertSame(0, app(BookingService::class)->settle($booking)->deposit->refunded_amount);
    }

    public function test_settle_command_settles_only_due_bookings(): void
    {
        $due = $this->completedWithDeposit();
        Carbon::setTestNow('2026-10-12 12:00:00');
        $notYet = Booking::factory()->create(['status' => S::Returned]);
        $notYet->transitionTo(S::Completed);
        $blocked = $this->completedWithDeposit();
        $blocked->damages()->create(['description' => 'Scratch', 'cost' => 100, 'status' => Damage::REPORTED]);

        Carbon::setTestNow('2026-10-14 00:00:00');
        $this->artisan('bookings:settle')->expectsOutputToContain('Settled 1 of 2 due bookings.')->assertSuccessful();

        $this->assertSame(S::Settled, $due->fresh()->status);
        $this->assertSame(S::Completed, $notYet->fresh()->status);
        $this->assertSame(S::Completed, $blocked->fresh()->status);
    }
}
