<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\Payment;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\BookingService;
use App\Services\Notifications\FcmPushDriver;
use App\Services\Notifications\LogPushDriver;
use App\Services\Notifications\PushDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Phase 7 backend for the customer app: device registration, push on booking changes, FCM driver, gateways list.
class PushAndDevicesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private object $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PushDriver::class, $this->push = new class implements PushDriver
        {
            public array $sent = [];

            public array $dead = [];

            public function send(string $token, string $title, string $body, array $data = []): bool
            {
                $this->sent[] = compact('token', 'title', 'body', 'data');

                return ! in_array($token, $this->dead, true);
            }
        });
    }

    public function test_devices_register_move_between_users_and_unregister(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertUnauthorized();
        $this->actingAs($a, 'sanctum')->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertCreated();
        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertOk();
        $this->postJson('/api/v1/devices', ['token' => '', 'platform' => 'symbian'])->assertUnprocessable()->assertJsonValidationErrors(['token', 'platform']);

        // Same phone, other account signs in: the token follows the new user.
        $this->actingAs($b, 'sanctum')->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertOk();
        $this->assertSame($b->id, DeviceToken::sole()->user_id);

        $this->actingAs($a, 'sanctum')->deleteJson('/api/v1/devices/tok-1')->assertNoContent();
        $this->assertSame(1, DeviceToken::count()); // not a's token any more
        $this->actingAs($b, 'sanctum')->deleteJson('/api/v1/devices/tok-1')->assertNoContent();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_confirm_and_cancel_push_in_the_users_language_and_drop_dead_tokens(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment, 'grand_total' => 1000, 'deposit_amount' => 0]);
        $booking->user->update(['locale' => 'de']);
        DeviceToken::create(['user_id' => $booking->user_id, 'token' => 'live', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $booking->user_id, 'token' => 'gone', 'platform' => 'ios']);
        $this->push->dead = ['gone'];
        Payment::create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'gateway' => 'x', 'gateway_ref' => 'r', 'amount' => 1000, 'currency_code' => $booking->currency_code, 'status' => Payment::SUCCEEDED]);

        app(BookingService::class)->confirm($booking);

        $this->assertCount(2, $this->push->sent);
        $this->assertSame('Buchung Nr. '.$booking->id.' ist bestätigt.', $this->push->sent[0]['body']);
        $this->assertSame(['type' => 'booking', 'booking_id' => (string) $booking->id, 'status' => 'confirmed'], $this->push->sent[0]['data']);
        $this->assertSame(['live'], DeviceToken::pluck('token')->all());

        app(BookingService::class)->cancel($booking);
        $this->assertSame('cancelled', end($this->push->sent)['data']['status']);
    }

    public function test_no_push_when_the_change_rolls_back(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment, 'grand_total' => 1000]);
        DeviceToken::create(['user_id' => $booking->user_id, 'token' => 'live', 'platform' => 'android']);

        try {
            app(BookingService::class)->confirm($booking); // unpaid: refused
        } catch (\DomainException) {
        }

        $this->assertSame([], $this->push->sent);
    }

    public function test_fcm_driver_signs_a_jwt_and_sends_http_v1(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/v1/projects/demo-app/messages:send' => Http::sequence()->push(['name' => 'ok'])->push(['error' => 'UNREGISTERED'], 404),
        ]);
        $driver = new FcmPushDriver(['project_id' => 'demo-app', 'client_email' => 'push@demo-app.iam.gserviceaccount.com', 'private_key' => $pem]);

        $this->assertTrue($driver->send('tok', 'Title', 'Body', ['booking_id' => '7']));
        $this->assertFalse($driver->send('stale', 'Title', 'Body'));

        Http::assertSent(function (HttpRequest $r) use ($key) {
            if (! str_contains($r->url(), 'oauth2')) {
                return false;
            }
            [$h, $c, $s] = explode('.', $r['assertion']);
            $claims = json_decode(base64_decode(strtr($c, '-_', '+/')), true);
            $valid = openssl_verify("$h.$c", base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4)), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256);

            return $valid === 1 && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
        });
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'messages:send') && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && ($r['message']['token'] ?? null) === 'tok' && $r['message']['data'] === ['booking_id' => '7']);
    }

    public function test_driver_selection_and_gateways_endpoint(): void
    {
        $this->app->forgetInstance(PushDriver::class);
        $this->app->offsetUnset(PushDriver::class);
        $this->app->register(AppServiceProvider::class, true);
        $this->assertInstanceOf(LogPushDriver::class, app(PushDriver::class)); // no FCM_CREDENTIALS

        $this->getJson('/api/v1/payments/gateways')->assertOk()->assertExactJson(['data' => []]);
        config(['services.razorpay' => ['key_id' => 'k', 'key_secret' => 's', 'webhook_secret' => 'w']]);
        $this->getJson('/api/v1/payments/gateways')->assertOk()->assertExactJson(['data' => ['razorpay']]);
    }
}
