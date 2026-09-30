<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

// Drivers against the providers' documented request/response and signature formats (no network: HTTP is faked).
class PaymentGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.stripe' => ['key' => 'pk_test_1', 'secret' => 'sk_test_1', 'webhook_secret' => 'whsec_1'],
            'services.razorpay' => ['key_id' => 'rzp_test_1', 'key_secret' => 'rzp_secret', 'webhook_secret' => 'rzp_whsec'],
        ]);
    }

    private function payment(): Payment
    {
        return (new Payment)->forceFill(['id' => 42, 'amount' => 503000, 'currency_code' => 'INR']);
    }

    private function request(string $payload, array $headers): Request
    {
        $server = collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all();

        return Request::create('/webhook', 'POST', [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], $payload);
    }

    public function test_configuration_detection(): void
    {
        $this->assertTrue(app(StripeGateway::class)->isConfigured());
        config(['services.stripe.webhook_secret' => null, 'services.razorpay.key_secret' => '']);
        $this->assertFalse(app(StripeGateway::class)->isConfigured());
        $this->assertFalse(app(RazorpayGateway::class)->isConfigured());
    }

    public function test_stripe_creates_payment_intent_in_minor_units(): void
    {
        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_123', 'client_secret' => 'pi_123_secret_x'])]);

        $intent = app(StripeGateway::class)->createIntent($this->payment());

        $this->assertSame(['ref' => 'pi_123', 'client' => ['client_secret' => 'pi_123_secret_x', 'publishable_key' => 'pk_test_1']], $intent);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer sk_test_1')
            && $r['amount'] == 503000 && $r['currency'] === 'inr' && $r['metadata']['payment_id'] == 42); // form-encoded: strings
    }

    public function test_stripe_webhook_signature_and_event_mapping(): void
    {
        $stripe = app(StripeGateway::class);
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_123', 'amount' => 503000, 'amount_received' => 503000, 'currency' => 'inr']]]);
        $sign = fn (int $t, string $secret = 'whsec_1') => "t=$t,v1=".hash_hmac('sha256', "$t.$payload", $secret);

        $this->assertSame(
            ['ref' => 'pi_123', 'status' => Payment::SUCCEEDED, 'amount' => 503000, 'currency' => 'INR'],
            $stripe->parseWebhook($this->request($payload, ['Stripe-Signature' => $sign(now()->timestamp)])),
        );

        // Several v1 signatures (secret rotation): any match is accepted.
        $rotated = 't='.now()->timestamp.',v1=deadbeef,v1='.hash_hmac('sha256', now()->timestamp.".$payload", 'whsec_1');
        $this->assertNotNull($stripe->parseWebhook($this->request($payload, ['Stripe-Signature' => $rotated])));

        $ignored = json_encode(['type' => 'customer.created', 'data' => ['object' => []]]);
        $t = now()->timestamp;
        $this->assertNull($stripe->parseWebhook($this->request($ignored, ['Stripe-Signature' => "t=$t,v1=".hash_hmac('sha256', "$t.$ignored", 'whsec_1')])));

        foreach ([$sign(now()->timestamp, 'wrong'), $sign(now()->timestamp - 301), 'garbage', ''] as $bad) {
            try {
                $stripe->parseWebhook($this->request($payload, ['Stripe-Signature' => $bad]));
                $this->fail("accepted bad signature: $bad");
            } catch (BadRequestHttpException) {
            }
        }
    }

    public function test_razorpay_creates_order_and_verifies_webhooks(): void
    {
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_9'])]);
        $razorpay = app(RazorpayGateway::class);

        $this->assertSame(['ref' => 'order_9', 'client' => ['order_id' => 'order_9', 'key_id' => 'rzp_test_1']], $razorpay->createIntent($this->payment()));
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_1:rzp_secret'))
            && $r['amount'] === 503000 && $r['currency'] === 'INR' && $r['receipt'] === 'payment_42');

        $payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['order_id' => 'order_9', 'amount' => 503000, 'currency' => 'INR']]]]);
        $this->assertSame(
            ['ref' => 'order_9', 'status' => Payment::SUCCEEDED, 'amount' => 503000, 'currency' => 'INR'],
            $razorpay->parseWebhook($this->request($payload, ['X-Razorpay-Signature' => hash_hmac('sha256', $payload, 'rzp_whsec')])),
        );

        $failed = str_replace('payment.captured', 'payment.failed', $payload);
        $this->assertSame(Payment::FAILED, $razorpay->parseWebhook($this->request($failed, ['X-Razorpay-Signature' => hash_hmac('sha256', $failed, 'rzp_whsec')]))['status']);

        $this->expectException(BadRequestHttpException::class);
        $razorpay->parseWebhook($this->request($payload, ['X-Razorpay-Signature' => hash_hmac('sha256', $payload.'x', 'rzp_whsec')]));
    }
}
