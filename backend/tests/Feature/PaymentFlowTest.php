<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Deposit;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\BookingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// Vehicle in Mumbai (INR), 1000/day, car deposit 500000 → a Mon-Thu booking owes 3000 + 500000 = 503000.
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // payment.timeout_minutes = 30

    private User $customer;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 00:00:00');
        config([
            'services.stripe' => ['key' => 'pk_test_1', 'secret' => 'sk_test_1', 'webhook_secret' => 'whsec_1'],
            'services.razorpay' => ['key_id' => 'rzp_test_1', 'key_secret' => 'rzp_secret', 'webhook_secret' => 'rzp_whsec'],
        ]);
        Http::fake([
            'api.stripe.com/v1/payment_intents' => fn () => Http::response(['id' => 'pi_'.Str::random(10), 'client_secret' => 'secret']),
            'api.razorpay.com/v1/orders' => fn () => Http::response(['id' => 'order_'.Str::random(10)]),
        ]);
        $this->vehicle = Vehicle::factory()->create();
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);
        $this->customer = User::factory()->create(['country_id' => Country::where('iso_code', 'IN')->value('id')]);
        $this->actingAs($this->customer, 'sanctum');
    }

    private function book(string $start = '2026-10-05T10:00:00Z', string $end = '2026-10-08T10:00:00Z'): Booking
    {
        return Booking::findOrFail($this->postJson('/api/v1/bookings', ['vehicle_id' => $this->vehicle->id, 'start_at' => $start, 'end_at' => $end])
            ->assertCreated()->json('data.id'));
    }

    private function intent(Booking $booking, string $gateway = 'stripe'): TestResponse
    {
        return $this->postJson('/api/v1/payments/intent', ['booking_id' => $booking->id, 'gateway' => $gateway]);
    }

    private function stripeEvent(Payment $payment, string $type = 'payment_intent.succeeded', ?int $amount = null): TestResponse
    {
        $amount ??= $payment->amount;
        $payload = json_encode(['type' => $type, 'data' => ['object' => [
            'id' => $payment->gateway_ref, 'amount' => $amount, 'amount_received' => $amount, 'currency' => strtolower($payment->currency_code),
        ]]]);
        $t = now()->timestamp;

        return $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t=$t,v1=".hash_hmac('sha256', "$t.$payload", 'whsec_1'),
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }

    private function balance(string $currency = 'INR'): int
    {
        return app(WalletService::class)->balance($this->customer->id, $currency);
    }

    public function test_gateway_intent_moves_draft_to_pending_payment_for_the_amount_due(): void
    {
        $booking = $this->book();

        $this->intent($booking)->assertCreated()
            ->assertJsonPath('data.booking_status', 'pending_payment')
            ->assertJsonPath('data.payment.amount', 503000)
            ->assertJsonPath('data.payment.currency_code', 'INR')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.client.client_secret', 'secret')
            ->assertJsonPath('data.client.publishable_key', 'pk_test_1');

        $this->assertStringStartsWith('pi_', Payment::first()->gateway_ref);
    }

    public function test_webhook_confirms_booking_and_is_idempotent(): void
    {
        $booking = $this->book();
        $this->intent($booking);
        $payment = Payment::first();

        for ($i = 0; $i < 3; $i++) { // gateways retry
            $this->stripeEvent($payment)->assertOk()->assertJson(['received' => true]);
        }

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(Payment::SUCCEEDED, $payment->fresh()->status);
        $this->assertSame(1, Deposit::where('booking_id', $booking->id)->where('status', Deposit::HELD)->count());
        $this->assertSame(0, WalletTransaction::count());
        $this->getJson("/api/v1/bookings/{$booking->id}")->assertJsonPath('data.amount_due', 0);
    }

    public function test_failed_then_succeeded_payment_and_rejected_webhooks(): void
    {
        $booking = $this->book();
        $this->intent($booking);
        $payment = Payment::first();

        $this->stripeEvent($payment, 'payment_intent.payment_failed')->assertOk();
        $this->assertSame([Payment::FAILED, BookingStatus::PendingPayment], [$payment->fresh()->status, $booking->fresh()->status]);

        $this->stripeEvent($payment, amount: 1)->assertOk(); // tampered amount: ignored
        $this->assertSame(Payment::FAILED, $payment->fresh()->status);

        $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertStatus(400);
        $this->postJson('/api/v1/payments/webhook/paypal', [])->assertNotFound();

        $this->stripeEvent($payment)->assertOk(); // customer retried the card on the same intent
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_razorpay_order_flow(): void
    {
        $booking = $this->book();
        $this->intent($booking, 'razorpay')->assertCreated()->assertJsonPath('data.client.key_id', 'rzp_test_1');
        $payment = Payment::first();

        $payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'order_id' => $payment->gateway_ref, 'amount' => 503000, 'currency' => 'INR',
        ]]]]);
        $this->call('POST', '/api/v1/payments/webhook/razorpay', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, 'rzp_whsec'), 'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_unconfigured_gateway_and_access_rules(): void
    {
        $booking = $this->book();
        config(['services.razorpay.key_secret' => null]);
        $this->intent($booking, 'razorpay')->assertUnprocessable()->assertJsonPath('message', __('messages.payment_gateway_unavailable'));
        $this->intent($booking, 'paypal')->assertUnprocessable()->assertJsonValidationErrors('gateway');

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->intent($booking)->assertForbidden();
        $this->assertSame(BookingStatus::Draft, $booking->fresh()->status);
    }

    public function test_gateway_failure_marks_payment_failed(): void
    {
        Http::swap(new HttpFactory); // drop setUp's successful stubs
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);
        $booking = $this->book();

        $this->intent($booking)->assertUnprocessable()->assertJsonPath('message', __('messages.payment_gateway_error'));
        $this->assertSame(Payment::FAILED, Payment::first()->status);
    }

    public function test_wallet_topup_then_pay_booking_from_wallet(): void
    {
        $booking = $this->book();
        $this->intent($booking, 'wallet')->assertUnprocessable()->assertJsonPath('message', __('messages.wallet_insufficient'));
        $this->assertSame(BookingStatus::Draft, $booking->fresh()->status); // rolled back

        $this->postJson('/api/v1/wallet/topup', ['amount' => 600000, 'gateway' => 'stripe'])->assertCreated()
            ->assertJsonPath('data.payment.currency_code', 'INR') // user's country currency
            ->assertJsonPath('data.payment.booking_id', null);
        $this->stripeEvent(Payment::first())->assertOk();
        $this->stripeEvent(Payment::first())->assertOk(); // retry: credited once
        $this->assertSame(600000, $this->balance());

        $this->intent($booking, 'wallet')->assertCreated()
            ->assertJsonPath('data.booking_status', 'confirmed')
            ->assertJsonPath('data.payment.status', 'succeeded')
            ->assertJsonPath('data.client', null);
        $this->assertSame(97000, $this->balance());

        $this->getJson('/api/v1/wallet')->assertOk()
            ->assertJsonPath('balances.0.currency_code', 'INR')
            ->assertJsonPath('balances.0.balance', 97000)
            ->assertJsonPath('data.0.type', 'payment')
            ->assertJsonPath('data.0.amount', -503000)
            ->assertJsonPath('data.1.type', 'topup')
            ->assertJsonStructure(['meta' => ['total']]);
    }

    public function test_topup_validation(): void
    {
        $this->postJson('/api/v1/wallet/topup', ['amount' => 0, 'gateway' => 'wallet', 'currency_code' => 'XYZ'])
            ->assertUnprocessable()->assertJsonValidationErrors(['amount', 'gateway', 'currency_code']);

        $this->actingAs(User::factory()->create(['country_id' => null]), 'sanctum');
        $this->postJson('/api/v1/wallet/topup', ['amount' => 100, 'gateway' => 'stripe'])
            ->assertUnprocessable()->assertJsonPath('message', __('messages.wallet_currency_required'));
        $this->postJson('/api/v1/wallet/topup', ['amount' => 100, 'gateway' => 'stripe', 'currency_code' => 'AED'])->assertCreated();
    }

    public function test_cancel_refunds_everything_paid_to_the_wallet(): void
    {
        $booking = $this->book();
        $this->intent($booking);
        $this->stripeEvent(Payment::first());

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk()
            ->assertJsonPath('data.deposit.status', 'released')
            ->assertJsonPath('data.amount_paid', 0)
            ->assertJsonPath('data.amount_due', 0);

        $this->assertSame(503000, $this->balance());
        $this->assertSame(WalletTransaction::REFUND, WalletTransaction::first()->type);
    }

    public function test_only_what_is_due_can_be_paid_and_overpayment_is_returned(): void
    {
        $booking = $this->book();
        $this->intent($booking);
        $this->intent($booking); // second intent while the first is still open
        [$first, $second] = Payment::orderBy('id')->get()->all();

        $this->stripeEvent($first);
        $this->intent($booking)->assertUnprocessable()->assertJsonPath('message', __('messages.payment_nothing_due'));

        $this->stripeEvent($second); // customer paid both
        $this->assertSame(503000, $this->balance());
        $this->getJson("/api/v1/bookings/{$booking->id}")->assertJsonPath('data.amount_due', 0)->assertJsonPath('data.amount_paid', 503000);
    }

    public function test_extension_on_confirmed_booking_is_paid_with_a_new_intent(): void
    {
        $booking = $this->book();
        $this->intent($booking);
        $this->stripeEvent(Payment::first());

        $this->postJson("/api/v1/bookings/{$booking->id}/extend", ['end_at' => '2026-10-09T10:00:00Z'])->assertJsonPath('data.amount_due', 1000);
        $this->intent($booking)->assertCreated()->assertJsonPath('data.payment.amount', 1000)->assertJsonPath('data.booking_status', 'confirmed');
        $this->stripeEvent(Payment::latest('id')->first());

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertJsonPath('data.amount_due', 0)->assertJsonPath('data.status', 'confirmed');
    }

    public function test_payment_timeout_cancels_unpaid_booking_and_late_payment_is_refunded(): void
    {
        $booking = $this->book();
        $this->intent($booking);
        $paid = $this->book('2026-10-20T10:00:00Z', '2026-10-21T10:00:00Z');
        $this->intent($paid);
        $this->stripeEvent(Payment::where('booking_id', $paid->id)->first());

        Carbon::setTestNow('2026-10-01 00:29:00');
        $this->artisan('payments:expire')->expectsOutputToContain('Cancelled 0')->assertSuccessful();

        Carbon::setTestNow('2026-10-01 00:31:00');
        $this->artisan('payments:expire')->expectsOutputToContain('Cancelled 1')->assertSuccessful();
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $paid->fresh()->status);
        $late = Payment::where('booking_id', $booking->id)->first();
        $this->assertSame(Payment::EXPIRED, $late->status);

        $this->stripeEvent($late)->assertOk(); // the customer's bank took the money anyway
        $this->assertSame(Payment::SUCCEEDED, $late->fresh()->status);
        $this->assertSame(503000, $this->balance());
    }

    public function test_settlement_releases_deposit_to_the_wallet(): void
    {
        $booking = $this->book();
        $this->intent($booking);
        $this->stripeEvent(Payment::first());
        $booking = $booking->fresh();
        foreach ([BookingStatus::PickedUp, BookingStatus::Returned, BookingStatus::Completed] as $status) {
            $booking->transitionTo($status);
        }
        $booking->damages()->create(['description' => 'Scratch', 'cost' => 20000, 'status' => 'approved']);

        Carbon::setTestNow('2026-10-04 01:00:00');
        app(BookingService::class)->settle($booking);

        $this->assertSame(480000, $this->balance());
        $this->assertSame(WalletTransaction::DEPOSIT_RELEASE, WalletTransaction::first()->type);
    }
}
