<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\WalletService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Checkout (MD §7.6): pays what is due through PaymentService::createBookingIntent, exactly like POST /payments/intent.
 * Wallet completes in-page; a card opens the configured gateway's checkout and the webhook confirms (D5).
 */
#[Layout('layouts.site')]
class Checkout extends Component
{
    public Booking $booking;

    public ?string $payError = null;

    public function mount(Booking $booking)
    {
        Gate::authorize('view', $booking);
        $this->booking = $booking;

        return $this->due() <= 0 && $booking->status !== BookingStatus::Draft
            ? $this->redirectRoute('confirmation', ['booking' => $booking->id])
            : null;
    }

    private function due(): int
    {
        $b = $this->booking->fresh();

        return $b->grand_total + $b->deposit_amount - $b->amountPaid();
    }

    public function payWithWallet(PaymentService $payments)
    {
        return $this->pay($payments, Payment::WALLET);
    }

    public function payWithCard(PaymentService $payments, string $gateway)
    {
        if (! in_array($gateway, $payments->gatewaysFor($this->booking->currency_code), true)) {
            $this->payError = __('messages.payment_gateway_unavailable');

            return null;
        }

        return $this->pay($payments, $gateway);
    }

    private function pay(PaymentService $payments, string $gateway)
    {
        Gate::authorize('update', $this->booking);
        $this->payError = null;

        try {
            ['client' => $client] = $payments->createBookingIntent($this->booking, $gateway);
        } catch (DomainException $e) {
            $this->payError = $e->getMessage();

            return null;
        }

        $confirmation = route('confirmation', ['booking' => $this->booking->id]);
        if ($client === null) { // wallet: already paid and confirmed by the service
            return $this->redirect($confirmation);
        }

        $this->dispatch('start-card-payment', gateway: $gateway, client: $client, returnUrl: $confirmation, name: config('app.name'));

        return null;
    }

    public function render(PaymentService $payments, WalletService $wallet): View
    {
        $booking = $this->booking->fresh(['vehicle.vehicleModel.brand', 'vehicle.branch', 'addons.addon']);

        return view('livewire.checkout', [
            'b' => $booking,
            'due' => $this->due(),
            'walletBalance' => $wallet->balance(auth()->id(), $booking->currency_code),
            'gateways' => $payments->gatewaysFor($booking->currency_code),
            'expired' => $booking->isExpiredDraft(),
            'payable' => in_array($booking->status, [BookingStatus::Draft, BookingStatus::PendingPayment, BookingStatus::Confirmed, BookingStatus::PickedUp], true),
        ])->title(__('site.checkout.title'));
    }
}
