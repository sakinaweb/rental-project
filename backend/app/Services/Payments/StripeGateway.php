<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

// Stripe PaymentIntents (https://docs.stripe.com/api/payment_intents) and signed webhooks
// (https://docs.stripe.com/webhooks#verify-manually). Amounts are already in the smallest currency unit.
class StripeGateway implements PaymentGateway
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret')) && filled(config('services.stripe.webhook_secret'));
    }

    public function createIntent(Payment $payment): array
    {
        $intent = Http::asForm()
            ->withToken(config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $payment->amount,
                'currency' => strtolower($payment->currency_code),
                'automatic_payment_methods' => ['enabled' => 'true'],
                'metadata' => ['payment_id' => $payment->id],
            ])
            ->throw()
            ->json();

        return [
            'ref' => $intent['id'],
            'client' => ['client_secret' => $intent['client_secret'], 'publishable_key' => config('services.stripe.key')],
        ];
    }

    public function parseWebhook(Request $request): ?array
    {
        $this->verify($request->getContent(), (string) $request->header('Stripe-Signature'));

        $event = $request->json()->all();
        $status = match ($event['type'] ?? null) {
            'payment_intent.succeeded' => Payment::SUCCEEDED,
            'payment_intent.payment_failed' => Payment::FAILED,
            default => null,
        };
        if (! $status) {
            return null;
        }

        $intent = $event['data']['object'];

        return [
            'ref' => $intent['id'],
            'status' => $status,
            'amount' => (int) ($status === Payment::SUCCEEDED ? $intent['amount_received'] : $intent['amount']),
            'currency' => strtoupper($intent['currency']),
        ];
    }

    private function verify(string $payload, string $header): void
    {
        preg_match('/t=(\d+)/', $header, $t);
        $timestamp = (int) ($t[1] ?? 0);
        preg_match_all('/v1=([a-f0-9]+)/', $header, $matches); // several during secret rotation
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) config('services.stripe.webhook_secret'));

        $valid = collect($matches[1])->contains(fn (string $sig) => hash_equals($expected, $sig));
        if (! $valid || abs(now()->timestamp - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            throw new BadRequestHttpException('Invalid Stripe signature.');
        }
    }
}
