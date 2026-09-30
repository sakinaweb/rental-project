<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

// Swappable payment provider (MD §5 driver pattern). Register new drivers in PaymentService::gateway().
interface PaymentGateway
{
    /** True when the credentials needed for intents and webhooks are configured. */
    public function isConfigured(): bool;

    /**
     * Creates the provider-side payment for a pending Payment.
     *
     * @return array{ref: string, client: array<string, mixed>} provider reference + what the app needs to complete payment
     */
    public function createIntent(Payment $payment): array;

    /**
     * Verifies the webhook signature (throws on failure) and extracts the payment outcome.
     * Returns null for events the platform doesn't act on.
     *
     * @return array{ref: string, status: string, amount: int, currency: string}|null status is Payment::SUCCEEDED or Payment::FAILED
     */
    public function parseWebhook(Request $request): ?array;
}
