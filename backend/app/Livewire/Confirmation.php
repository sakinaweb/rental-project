<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Shows only the stored booking status (set by the wallet payment or the gateway webhook); never assumes success.
#[Layout('layouts.site')]
class Confirmation extends Component
{
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        Gate::authorize('view', $booking);
        $this->booking = $booking;
    }

    public function render(): View
    {
        $booking = $this->booking->fresh(['vehicle.vehicleModel.brand', 'vehicle.branch', 'addons.addon']);
        $state = match (true) {
            in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::NoShow], true) => 'failed',
            in_array($booking->status, [BookingStatus::Draft, BookingStatus::PendingPayment], true) => 'waiting',
            default => 'confirmed',
        };

        return view('livewire.confirmation', ['b' => $booking, 'state' => $state])->title(__('site.confirmation.title'));
    }
}
