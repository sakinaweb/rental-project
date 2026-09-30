<?php

namespace App\Livewire\Account;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\VehicleSearch;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Booking detail: cancel (refund to wallet) and extend through BookingService, as POST /bookings/{id}/cancel|extend.
#[Layout('layouts.site')]
class BookingShow extends Component
{
    public Booking $booking;

    public ?string $newEnd = null;

    public ?string $actionError = null;

    public ?string $notice = null;

    public function mount(Booking $booking): void
    {
        Gate::authorize('view', $booking);
        $this->booking = $booking;
    }

    public function cancel(BookingService $bookings): void
    {
        $this->run(fn () => $bookings->cancel($this->booking), __('site.booking.cancelled_notice'));
    }

    public function extend(BookingService $bookings): void
    {
        // Same rule as POST /bookings/{id}/extend: the new end must be after the current end.
        $validator = Validator::make(['end_at' => $this->newEnd], ['end_at' => ['required', 'date', 'after:'.$this->booking->fresh()->end_at->toIso8601String()]]);
        if ($validator->fails()) {
            $this->actionError = $validator->errors()->first('end_at');

            return;
        }

        $this->run(fn () => $bookings->extend($this->booking, VehicleSearch::toAppTime($this->newEnd)), __('site.booking.extended_notice'));
    }

    private function run(callable $action, string $notice): void
    {
        Gate::authorize('update', $this->booking);
        $this->actionError = $this->notice = null;

        try {
            $action();
            $this->notice = $notice;
            $this->newEnd = null;
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function render(): View
    {
        $b = $this->booking->fresh(['vehicle.vehicleModel.brand', 'vehicle.branch', 'addons.addon', 'deposit']);

        return view('livewire.account.booking-show', [
            'b' => $b,
            'due' => $b->grand_total + $b->deposit_amount - $b->amountPaid(),
            'canCancel' => $b->status->canTransitionTo(BookingStatus::Cancelled),
            'canExtend' => in_array($b->status, [BookingStatus::Draft, BookingStatus::Confirmed, BookingStatus::PickedUp], true) && ! $b->isExpiredDraft(),
        ])->title(__('site.booking.title', ['id' => $b->id]));
    }
}
