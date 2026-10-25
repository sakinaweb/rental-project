<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Damage;
use App\Models\Deposit;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private const TOTALS = ['subtotal', 'tax_total', 'discount_total', 'grand_total'];

    /**
     * Transactions that claim a vehicle's time take gap locks on the bookings index, so simultaneous bookings (even of
     * different vehicles) can deadlock in MySQL. Each deadlock lets one through; the others are re-run (Phase 12,
     * BookingRaceTest) instead of failing the customer's request.
     */
    public const DEADLOCK_ATTEMPTS = 5;

    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private CountryPackService $packs,
        private WalletService $wallet,
        private NotificationService $notifications,
        private LedgerService $ledger,
    ) {}

    /**
     * Pricing quote (MD §5) plus the refundable deposit from the country pack, per vehicle type.
     *
     * @param  array<int, int>  $addons  addon_id => quantity
     * @return array<string, int|string>
     */
    public function quote(Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to, array $addons = [], ?Coupon $coupon = null, ?CarbonInterface $couponCheckedAt = null): array
    {
        $quote = $this->pricing->quote($vehicle, $from, $to, $addons, $coupon, $couponCheckedAt);
        $deposits = $this->packs->rulesFor($vehicle->branch->location->city->country_id)['deposit'] ?? [];

        return $quote + ['deposit_amount' => (int) ($deposits[$vehicle->vehicleModel->category->type] ?? 0)];
    }

    /**
     * Creates a draft booking (MD §4 "locks availability"): availability check, coupon usage and insert
     * happen in one transaction while the vehicle row is locked, so concurrent attempts cannot double-book.
     *
     * @param  array<int, int>  $addons
     */
    public function create(User $user, Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to, array $addons = [], ?Coupon $coupon = null, ?int $pickupLocationId = null, ?int $dropoffLocationId = null): Booking
    {
        return DB::transaction(function () use ($user, $vehicle, $from, $to, $addons, $coupon, $pickupLocationId, $dropoffLocationId) {
            // Coupon first: bookings sharing a coupon queue on its row before taking any bookings-index locks, and the
            // usage count is read only after that lock is held (a fresh snapshot).
            if ($coupon) {
                $this->ensureCouponUsable($coupon);
            }
            if (! $this->availability->isAvailable($vehicle->id, $from, $to)) {
                throw new DomainException(__('messages.vehicle_unavailable'));
            }

            $quote = $this->quote($vehicle, $from, $to, $addons, $coupon);
            $branchLocation = $vehicle->branch->location_id;

            $booking = Booking::create([
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'coupon_id' => $coupon?->id,
                'pickup_location_id' => $pickupLocationId ?? $branchLocation,
                'dropoff_location_id' => $dropoffLocationId ?? $branchLocation,
                'start_at' => $from,
                'end_at' => $to,
                'status' => BookingStatus::Draft,
                'currency_code' => $quote['currency_code'],
                'exchange_rate' => 1,
                'deposit_amount' => $quote['deposit_amount'],
                ...array_intersect_key($quote, array_flip(self::TOTALS)),
            ]);

            $prices = Addon::findMany(array_keys($addons))->pluck('price', 'id');
            foreach ($addons as $id => $qty) {
                $booking->addons()->create(['addon_id' => $id, 'qty' => $qty, 'price' => $prices[$id]]);
            }

            return $booking;
        }, self::DEADLOCK_ATTEMPTS);
    }

    /** MD §4 "confirm after payment": succeeded payments must cover the total plus the deposit. */
    public function confirm(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = $this->lock($booking);

            if ($booking->status === BookingStatus::PendingPayment && $booking->amountPaid() < $booking->grand_total + $booking->deposit_amount) {
                throw new DomainException(__('messages.booking_payment_required'));
            }
            $booking->transitionTo(BookingStatus::Confirmed);
            $this->notifyAfterCommit($booking);

            if ($booking->deposit_amount > 0) {
                $booking->deposit()->create(['amount' => $booking->deposit_amount, 'status' => Deposit::HELD]);
            }

            return $booking;
        });
    }

    /**
     * Frees the vehicle, releases a held deposit, and refunds everything paid (rental + deposit) to the wallet
     * in the booking currency. No cancellation fee (project owner decision, Phase 4).
     */
    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = $this->lock($booking);
            $booking->transitionTo(BookingStatus::Cancelled);
            $this->notifyAfterCommit($booking);
            if ($booking->deposit?->status === Deposit::HELD) {
                $booking->deposit->release($booking->deposit->amount);
            }

            $paid = $booking->amountPaid();
            if ($paid > 0) {
                $this->wallet->credit($booking->user_id, $paid, $booking->currency_code, WalletTransaction::REFUND, 'booking', $booking->id);
            }

            return $booking;
        });
    }

    /**
     * Moves end_at later. The already-booked period keeps its price; the booking is charged the difference between
     * quotes for the new and old periods at current rates (same add-ons, coupon judged at booking time).
     */
    public function extend(Booking $booking, CarbonInterface $newEnd): Booking
    {
        return DB::transaction(function () use ($booking, $newEnd) {
            $booking = $this->lock($booking);

            if (! in_array($booking->status, [BookingStatus::Draft, BookingStatus::Confirmed, BookingStatus::PickedUp], true)) {
                throw new DomainException(__('messages.booking_invalid_status'));
            }
            if ($booking->isExpiredDraft()) {
                throw new DomainException(__('messages.booking_draft_expired'));
            }
            if (! $this->availability->isAvailable($booking->vehicle_id, $booking->end_at, $newEnd, $booking->id)) {
                throw new DomainException(__('messages.vehicle_unavailable'));
            }

            $addons = $booking->addons->pluck('qty', 'addon_id')->all();
            $quote = fn (CarbonInterface $end) => $this->pricing->quote($booking->vehicle, $booking->start_at, $end, $addons, $booking->coupon, $booking->created_at);
            $old = $quote($booking->end_at);
            $new = $quote($newEnd);

            $booking->end_at = Carbon::instance($newEnd);
            foreach (self::TOTALS as $field) {
                $booking->$field += $new[$field] - $old[$field];
            }
            $booking->save();

            return $booking;
        }, self::DEADLOCK_ATTEMPTS);
    }

    /**
     * completed → settled after the damage hold window (MD §6): the deposit is released minus approved damages, and the
     * revenue waterfall is posted to the earnings ledger in the same transaction (MD §5, Phase 9).
     */
    public function settle(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = $this->lock($booking);
            $holdHours = (int) Setting::valueOf('booking.damage_hold_hours', 72);

            if ($booking->status === BookingStatus::Completed && $booking->completed_at?->gt(now()->subHours($holdHours))) {
                throw new DomainException(__('messages.booking_hold_window'));
            }
            if ($booking->damages()->where('status', Damage::REPORTED)->exists()) {
                throw new DomainException(__('messages.booking_damage_pending'));
            }
            $booking->transitionTo(BookingStatus::Settled);

            $kept = 0; // deposit kept for approved damages
            if ($booking->deposit?->status === Deposit::HELD) {
                $charged = (int) $booking->damages()->where('status', Damage::APPROVED)->sum('cost');
                $refund = max(0, $booking->deposit->amount - $charged);
                $kept = $booking->deposit->amount - $refund;
                $booking->deposit->release($refund);
                if ($refund > 0) {
                    $this->wallet->credit($booking->user_id, $refund, $booking->currency_code, WalletTransaction::DEPOSIT_RELEASE, 'booking', $booking->id);
                }
            }
            $this->ledger->postBookingCompletion($booking, $kept);

            return $booking;
        }, self::DEADLOCK_ATTEMPTS); // ledger accounts are created on first use (insertOrIgnore): same gap-lock risk
    }

    /**
     * Lifecycle steps without money effects (picked_up, returned, completed, no_show), under the booking lock and
     * the §6 guards. Confirm, cancel and settle move money and have their own methods.
     */
    public function advance(Booking $booking, BookingStatus $to): Booking
    {
        if (! in_array($to, [BookingStatus::PickedUp, BookingStatus::Returned, BookingStatus::Completed, BookingStatus::NoShow], true)) {
            throw new DomainException(__('messages.booking_invalid_status'));
        }

        return DB::transaction(function () use ($booking, $to) {
            $booking = $this->lock($booking);
            $booking->transitionTo($to);

            return $booking;
        });
    }

    /**
     * POST /admin/bookings/{id}/assign (decision D2): moves a booking that hasn't started to another vehicle in the
     * same country (same currency), re-checking availability under the new vehicle's row lock. Price is unchanged.
     */
    public function reassign(Booking $booking, Vehicle $vehicle): Booking
    {
        return DB::transaction(function () use ($booking, $vehicle) {
            $booking = $this->lock($booking);

            if (! in_array($booking->status, [BookingStatus::Draft, BookingStatus::PendingPayment, BookingStatus::Confirmed], true)) {
                throw new DomainException(__('messages.booking_invalid_status'));
            }
            if ($vehicle->branch->location->city->country_id !== $booking->vehicle->branch->location->city->country_id
                || ! $this->availability->isAvailable($vehicle->id, $booking->start_at, $booking->end_at)) {
                throw new DomainException(__('messages.vehicle_unavailable'));
            }

            $booking->update(['vehicle_id' => $vehicle->id]);

            return $booking;
        }, self::DEADLOCK_ATTEMPTS);
    }

    // Push to the customer app once the change is committed (Phase 7); a rolled-back change sends nothing.
    private function notifyAfterCommit(Booking $booking): void
    {
        DB::afterCommit(fn () => $this->notifications->bookingStatusChanged($booking->fresh()));
    }

    private function lock(Booking $booking): Booking
    {
        return Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
    }

    /** Coupon row is locked so two bookings can't both take the last use. Cancelled bookings and expired drafts don't count. */
    private function ensureCouponUsable(Coupon $coupon): void
    {
        $coupon = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
        if ($coupon->usage_limit === null) {
            return;
        }

        $used = Booking::where('coupon_id', $coupon->id)
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->notExpiredDraft()
            ->count();

        if ($used >= $coupon->usage_limit) {
            throw new DomainException(__('messages.coupon_used_up'));
        }
    }
}
