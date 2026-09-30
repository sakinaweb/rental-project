<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\StripeGateway;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentService
{
    public const GATEWAYS = ['stripe', 'razorpay'];

    public function __construct(private BookingService $bookings, private WalletService $wallet) {}

    /** @return list<string> gateways with keys and webhook secret configured (what the website may offer) */
    public function configuredGateways(): array
    {
        return array_values(array_filter(self::GATEWAYS, function (string $name) {
            try {
                return (bool) $this->gateway($name);
            } catch (DomainException) {
                return false;
            }
        }));
    }

    public function gateway(string $name): PaymentGateway
    {
        $gateway = match ($name) {
            'stripe' => app(StripeGateway::class),
            'razorpay' => app(RazorpayGateway::class),
            default => null,
        };

        if (! $gateway?->isConfigured()) {
            throw new DomainException(__('messages.payment_gateway_unavailable'));
        }

        return $gateway;
    }

    /**
     * POST /payments/intent: collects what is still due on a booking (total + deposit − succeeded payments).
     * A draft moves to pending_payment. With the wallet the money moves immediately and a pending_payment booking
     * is confirmed; with a gateway a pending Payment is created and the webhook completes it.
     *
     * @return array{payment: Payment, client: array<string, mixed>|null}
     */
    public function createBookingIntent(Booking $booking, string $gatewayName): array
    {
        $gateway = $gatewayName === Payment::WALLET ? null : $this->gateway($gatewayName);

        $payment = DB::transaction(function () use ($booking, $gateway, $gatewayName) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status === BookingStatus::Draft) {
                $booking->transitionTo(BookingStatus::PendingPayment);
            } elseif (! in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Confirmed, BookingStatus::PickedUp], true)) {
                throw new DomainException(__('messages.booking_invalid_status'));
            }

            $due = $booking->grand_total + $booking->deposit_amount - $booking->amountPaid();
            if ($due <= 0) {
                throw new DomainException(__('messages.payment_nothing_due'));
            }

            if ($gateway) {
                return Payment::create([
                    'user_id' => $booking->user_id, 'booking_id' => $booking->id, 'gateway' => $gatewayName,
                    'amount' => $due, 'currency_code' => $booking->currency_code, 'status' => Payment::PENDING,
                ]);
            }

            $walletTx = $this->wallet->debit($booking->user_id, $due, $booking->currency_code, WalletTransaction::PAYMENT, 'booking', $booking->id);
            $payment = Payment::create([
                'user_id' => $booking->user_id, 'booking_id' => $booking->id, 'gateway' => Payment::WALLET,
                'gateway_ref' => 'wallet-tx-'.$walletTx->id, 'amount' => $due, 'currency_code' => $booking->currency_code,
                'status' => Payment::SUCCEEDED,
            ]);
            if ($booking->status === BookingStatus::PendingPayment) {
                $this->bookings->confirm($booking);
            }

            return $payment;
        });

        if (! $gateway) {
            return ['payment' => $payment, 'client' => null];
        }

        return ['payment' => $payment, 'client' => $this->openIntent($gateway, $payment)];
    }

    /**
     * POST /wallet/topup: a gateway payment with no booking; the webhook credits the wallet.
     *
     * @return array{payment: Payment, client: array<string, mixed>}
     */
    public function createTopup(User $user, int $amount, string $currency, string $gatewayName): array
    {
        $gateway = $this->gateway($gatewayName);
        $payment = Payment::create([
            'user_id' => $user->id, 'gateway' => $gatewayName, 'amount' => $amount,
            'currency_code' => $currency, 'status' => Payment::PENDING,
        ]);

        return ['payment' => $payment, 'client' => $this->openIntent($gateway, $payment)];
    }

    /**
     * POST /payments/webhook/{gateway}. Idempotent (MD §8): the payment row is locked and a payment that already
     * succeeded is never processed twice, however often the gateway retries.
     */
    public function handleWebhook(string $gatewayName, Request $request): void
    {
        $event = $this->gateway($gatewayName)->parseWebhook($request);
        if (! $event) {
            return;
        }

        DB::transaction(function () use ($gatewayName, $event) {
            $payment = Payment::where('gateway', $gatewayName)->where('gateway_ref', $event['ref'])->lockForUpdate()->first();

            if (! $payment || $payment->status === Payment::SUCCEEDED) {
                return;
            }
            if ($event['status'] === Payment::FAILED) {
                $payment->update(['status' => Payment::FAILED]);

                return;
            }
            if ($event['amount'] !== $payment->amount || $event['currency'] !== $payment->currency_code) {
                Log::warning('Payment webhook amount mismatch', ['payment_id' => $payment->id, 'event' => $event]);

                return;
            }

            $payment->update(['status' => Payment::SUCCEEDED]);
            $this->applySucceeded($payment);
        });
    }

    /**
     * Payment timeout: unpaid pending_payment bookings are cancelled after settings.payment.timeout_minutes (default 30),
     * measured from entering pending_payment (updated_at: nothing else updates a booking in that status).
     * Cancellation refunds any partial payment to the wallet; open gateway payments become expired.
     */
    public function expireUnpaid(): int
    {
        $timeout = (int) Setting::valueOf('payment.timeout_minutes', 30);
        $expired = 0;

        foreach (Booking::where('status', BookingStatus::PendingPayment->value)->where('updated_at', '<=', now()->subMinutes($timeout))->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $booking = Booking::whereKey($id)->lockForUpdate()->first();
                if ($booking?->status !== BookingStatus::PendingPayment) {
                    return; // paid (confirmed) in the meantime
                }
                $this->bookings->cancel($booking);
                $booking->payments()->where('status', Payment::PENDING)->update(['status' => Payment::EXPIRED]);
                $expired++;
            });
        }

        return $expired;
    }

    private function applySucceeded(Payment $payment): void
    {
        if (! $payment->booking_id) {
            $this->wallet->credit($payment->user_id, $payment->amount, $payment->currency_code, WalletTransaction::TOPUP, 'payment', $payment->id);

            return;
        }

        $booking = Booking::whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();

        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
            // Money arrived after the booking ended (e.g. payment timeout): give it back.
            $this->wallet->credit($booking->user_id, $payment->amount, $payment->currency_code, WalletTransaction::REFUND, 'booking', $booking->id);

            return;
        }

        $owed = $booking->grand_total + $booking->deposit_amount;
        if ($booking->status === BookingStatus::PendingPayment && $booking->amountPaid() >= $owed) {
            $this->bookings->confirm($booking);
        }

        // Two intents both completed (or the price dropped): return what was paid beyond the total + deposit.
        $excess = $booking->amountPaid() - $owed;
        if ($excess > 0) {
            $this->wallet->credit($booking->user_id, $excess, $booking->currency_code, WalletTransaction::REFUND, 'booking', $booking->id);
        }
    }

    /** @return array<string, mixed> */
    private function openIntent(PaymentGateway $gateway, Payment $payment): array
    {
        try {
            $intent = $gateway->createIntent($payment);
        } catch (Throwable $e) {
            $payment->update(['status' => Payment::FAILED]);
            Log::error('Payment intent failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            throw new DomainException(__('messages.payment_gateway_error'));
        }

        $payment->update(['gateway_ref' => $intent['ref']]);

        return $intent['client'];
    }
}
