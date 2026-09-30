<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

// Razorpay Orders (https://razorpay.com/docs/api/orders/) and webhooks signed with HMAC-SHA256 of the raw body
// (https://razorpay.com/docs/webhooks/validate-test/). The order id is our gateway_ref.
class RazorpayGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id'))
            && filled(config('services.razorpay.key_secret'))
            && filled(config('services.razorpay.webhook_secret'));
    }

    public function createIntent(Payment $payment): array
    {
        $order = Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $payment->amount,
                'currency' => $payment->currency_code,
                'receipt' => 'payment_'.$payment->id,
                'notes' => ['payment_id' => $payment->id],
            ])
            ->throw()
            ->json();

        return [
            'ref' => $order['id'],
            'client' => ['order_id' => $order['id'], 'key_id' => config('services.razorpay.key_id')],
        ];
    }

    public function parseWebhook(Request $request): ?array
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) config('services.razorpay.webhook_secret'));
        if (! hash_equals($expected, (string) $request->header('X-Razorpay-Signature'))) {
            throw new BadRequestHttpException('Invalid Razorpay signature.');
        }

        $event = $request->json()->all();
        $status = match ($event['event'] ?? null) {
            'payment.captured', 'order.paid' => Payment::SUCCEEDED,
            'payment.failed' => Payment::FAILED,
            default => null,
        };
        if (! $status) {
            return null;
        }

        $payment = $event['payload']['payment']['entity'];

        return [
            'ref' => $payment['order_id'],
            'status' => $status,
            'amount' => (int) $payment['amount'],
            'currency' => strtoupper($payment['currency']),
        ];
    }
}
