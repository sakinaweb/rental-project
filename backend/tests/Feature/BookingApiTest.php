<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// 2026-10-05 is a Monday. Vehicle: Mumbai (IN, INR), car; 1000/day; IN car deposit 500000.
class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Vehicle $vehicle;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 00:00:00');
        $this->vehicle = Vehicle::factory()->create();
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);
        $this->customer = User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + ['vehicle_id' => $this->vehicle->id, 'start_at' => '2026-10-05T10:00:00Z', 'end_at' => '2026-10-08T10:00:00Z'];
    }

    private function book(array $overrides = [], ?User $user = null): Booking
    {
        $id = $this->actingAs($user ?? $this->customer, 'sanctum')
            ->postJson('/api/v1/bookings', $this->payload($overrides))->assertCreated()->json('data.id');

        return Booking::findOrFail($id);
    }

    private function pay(Booking $booking, int $amount, string $currency = 'INR'): void
    {
        Payment::create(['booking_id' => $booking->id, 'gateway' => 'test', 'gateway_ref' => uniqid(), 'amount' => $amount, 'currency_code' => $currency, 'status' => Payment::SUCCEEDED]);
    }

    public function test_quote_is_public_and_includes_deposit(): void
    {
        $this->postJson('/api/v1/bookings/quote', $this->payload())->assertOk()
            ->assertJsonPath('data.currency_code', 'INR')
            ->assertJsonPath('data.grand_total', 3000)
            ->assertJsonPath('data.deposit_amount', 500000);
    }

    public function test_create_prices_the_draft_and_locks_the_vehicle(): void
    {
        $helmet = Addon::create(['name' => 'Helmet', 'price' => 200, 'type' => Addon::PER_DAY]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/bookings', $this->payload(['addons' => [['id' => $helmet->id, 'qty' => 2]]]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', 3000 + 1200)
            ->assertJsonPath('data.grand_total', 4200)
            ->assertJsonPath('data.deposit_amount', 500000)
            ->assertJsonPath('data.amount_due', 504200)
            ->assertJsonPath('data.addons.0.qty', 2)
            ->assertJsonPath('data.addons.0.price', 200)
            ->assertJsonPath('data.pickup_location_id', $this->vehicle->branch->location_id)
            ->assertJsonPath('data.expires_at', '2026-10-01T00:15:00.000000Z');

        $this->assertSame($this->customer->id, Booking::find($response->json('data.id'))->user_id);

        // Overlapping second attempt, even by someone else, is refused.
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/bookings', $this->payload(['start_at' => '2026-10-07T10:00:00Z', 'end_at' => '2026-10-09T10:00:00Z']))
            ->assertUnprocessable()->assertJsonPath('message', __('messages.vehicle_unavailable'));
    }

    public function test_create_validation(): void
    {
        $this->actingAs($this->customer, 'sanctum');

        $this->postJson('/api/v1/bookings', $this->payload(['start_at' => '2026-09-30T10:00:00Z']))->assertUnprocessable()->assertJsonValidationErrors('start_at');
        $this->postJson('/api/v1/bookings', $this->payload(['end_at' => '2026-10-05T09:00:00Z']))->assertUnprocessable()->assertJsonValidationErrors('end_at');
        $this->postJson('/api/v1/bookings', $this->payload(['addons' => [['id' => 1, 'qty' => 0]]]))->assertUnprocessable()->assertJsonValidationErrors('addons.0.qty');
        $this->postJson('/api/v1/bookings', $this->payload(['coupon_code' => 'NOPE']))->assertUnprocessable()->assertJsonPath('message', __('messages.coupon_invalid'));
        $this->postJson('/api/v1/bookings', $this->payload(['addons' => [['id' => 999999, 'qty' => 1]]]))->assertUnprocessable()->assertJsonPath('message', __('messages.addon_invalid'));

        $this->vehicle->update(['status' => 'maintenance']);
        $this->postJson('/api/v1/bookings', $this->payload())->assertNotFound();
        $this->postJson('/api/v1/bookings/quote', $this->payload())->assertNotFound();

        $this->assertSame(0, Booking::count());
    }

    public function test_guests_cannot_book(): void
    {
        $this->postJson('/api/v1/bookings', $this->payload())->assertUnauthorized();
        $this->getJson('/api/v1/bookings')->assertUnauthorized();
    }

    public function test_expired_draft_stops_blocking_and_cannot_progress(): void
    {
        $draft = $this->book();

        Carbon::setTestNow('2026-10-01 00:14:00');
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/bookings', $this->payload())->assertUnprocessable();

        Carbon::setTestNow('2026-10-01 00:16:00');
        $this->book([], User::factory()->create());

        $this->assertTrue($draft->fresh()->isExpiredDraft());
        $this->expectExceptionMessage(__('messages.booking_draft_expired'));
        $draft->fresh()->transitionTo(BookingStatus::PendingPayment);
    }

    public function test_customers_see_only_their_own_bookings_admins_see_all(): void
    {
        $mine = $this->book();
        $other = User::factory()->create();
        $theirs = $this->book(['start_at' => '2026-10-20T10:00:00Z', 'end_at' => '2026-10-21T10:00:00Z'], $other);

        $this->actingAs($this->customer, 'sanctum');
        $this->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/v1/bookings/{$theirs->id}")->assertForbidden();
        $this->postJson("/api/v1/bookings/{$theirs->id}/cancel")->assertForbidden();
        $this->getJson('/api/v1/admin/bookings')->assertForbidden();

        $this->actingAs(User::factory()->role(Role::ADMIN)->create(), 'sanctum');
        $this->getJson("/api/v1/bookings/{$theirs->id}")->assertOk();
        $this->getJson('/api/v1/admin/bookings')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.user.id', $other->id);
    }

    public function test_confirm_requires_pending_payment_and_full_payment_then_holds_deposit(): void
    {
        $booking = $this->book();
        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")->assertUnprocessable()->assertJsonPath('message', __('messages.booking_invalid_status'));

        $booking->transitionTo(BookingStatus::PendingPayment); // Phase 4: payment intent
        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")->assertUnprocessable()->assertJsonPath('message', __('messages.booking_payment_required'));

        $this->pay($booking, 3000);
        $this->pay($booking, 500000, 'USD'); // wrong currency doesn't count
        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")->assertUnprocessable();

        $this->pay($booking, 500000);
        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.amount_due', 0)
            ->assertJsonPath('data.deposit.status', 'held')
            ->assertJsonPath('data.deposit.amount', 500000);

        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")->assertUnprocessable();
    }

    public function test_cancel_from_allowed_states_releases_deposit(): void
    {
        $draft = $this->book();
        $this->postJson("/api/v1/bookings/{$draft->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/bookings/{$draft->id}/cancel")->assertUnprocessable();

        $confirmed = $this->book();
        $confirmed->transitionTo(BookingStatus::PendingPayment);
        $this->pay($confirmed, 503000);
        $this->postJson("/api/v1/bookings/{$confirmed->id}/confirm")->assertOk();
        $this->postJson("/api/v1/bookings/{$confirmed->id}/cancel")->assertOk()
            ->assertJsonPath('data.amount_due', 0)
            ->assertJsonPath('data.deposit.status', 'released')
            ->assertJsonPath('data.deposit.refunded_amount', 500000);

        $pickedUp = $this->book();
        foreach ([BookingStatus::PendingPayment, BookingStatus::Confirmed, BookingStatus::PickedUp] as $s) {
            $pickedUp->transitionTo($s);
        }
        $this->postJson("/api/v1/bookings/{$pickedUp->id}/cancel")->assertUnprocessable();
    }

    public function test_extend_charges_only_the_difference_and_respects_availability(): void
    {
        $booking = $this->book(); // Mon-Thu, 3000
        PricingRule::first()->update(['base_rate' => 2000]); // rates go up after booking

        // 3 -> 4 days: +1 day at the current rate (quote(4d) - quote(3d) = 8000 - 6000), original 3000 kept.
        $this->postJson("/api/v1/bookings/{$booking->id}/extend", ['end_at' => '2026-10-09T10:00:00Z'])->assertOk()
            ->assertJsonPath('data.end_at', '2026-10-09T10:00:00.000000Z')
            ->assertJsonPath('data.grand_total', 5000)
            ->assertJsonPath('data.deposit_amount', 500000);

        // Another customer books from Sat 10:00; extending to Sat 09:30 falls inside the 60-min buffer.
        $this->book(['start_at' => '2026-10-10T10:00:00Z', 'end_at' => '2026-10-11T10:00:00Z'], User::factory()->create());
        $this->actingAs($this->customer, 'sanctum');
        $this->postJson("/api/v1/bookings/{$booking->id}/extend", ['end_at' => '2026-10-10T09:30:00Z'])->assertUnprocessable()->assertJsonPath('message', __('messages.vehicle_unavailable'));
        $this->postJson("/api/v1/bookings/{$booking->id}/extend", ['end_at' => '2026-10-10T09:00:00Z'])->assertOk();
        $this->postJson("/api/v1/bookings/{$booking->id}/extend", ['end_at' => '2026-10-08T10:00:00Z'])->assertUnprocessable()->assertJsonValidationErrors('end_at');

        $awaitingPayment = $this->book(['start_at' => '2026-10-20T10:00:00Z', 'end_at' => '2026-10-21T10:00:00Z']);
        $awaitingPayment->transitionTo(BookingStatus::PendingPayment);
        $this->postJson("/api/v1/bookings/{$awaitingPayment->id}/extend", ['end_at' => '2026-10-22T10:00:00Z'])
            ->assertUnprocessable()->assertJsonPath('message', __('messages.booking_invalid_status'));
    }

    public function test_coupon_usage_limit_counts_live_bookings_only(): void
    {
        Coupon::create(['code' => 'ONCE', 'type' => Coupon::FIXED, 'value' => 100, 'usage_limit' => 1]);
        $first = $this->book(['coupon_code' => 'ONCE']);
        $this->assertSame(2900, $first->grand_total);

        $later = ['coupon_code' => 'ONCE', 'start_at' => '2026-10-20T10:00:00Z', 'end_at' => '2026-10-21T10:00:00Z'];
        $this->postJson('/api/v1/bookings', $this->payload($later))->assertUnprocessable()->assertJsonPath('message', __('messages.coupon_used_up'));

        $this->postJson("/api/v1/bookings/{$first->id}/cancel")->assertOk();
        $second = $this->book($later);

        Carbon::setTestNow('2026-10-01 00:30:00'); // second is now an expired draft, so the use frees up again
        $this->book(['coupon_code' => 'ONCE', 'start_at' => '2026-10-25T10:00:00Z', 'end_at' => '2026-10-26T10:00:00Z']);
        $this->assertTrue($second->fresh()->isExpiredDraft());
    }
}
