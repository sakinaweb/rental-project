<?php

namespace App\Enums;

/**
 * Booking lifecycle (MD §6):
 * draft → pending_payment → confirmed → picked_up → returned → completed → (damage hold window) → settled,
 * with cancelled (from draft, pending_payment, confirmed) and no_show (from confirmed).
 *
 * Approved deviation: the §6 diagram draws cancelled from confirmed only. Cancelling from draft and
 * pending_payment as well was explicitly approved by the project owner in Phase 3.
 */
enum BookingStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case PickedUp = 'picked_up';
    case Returned = 'returned';
    case Completed = 'completed';
    case Settled = 'settled';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Draft => [self::PendingPayment, self::Cancelled],
            self::PendingPayment => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::PickedUp, self::Cancelled, self::NoShow],
            self::PickedUp => [self::Returned],
            self::Returned => [self::Completed],
            self::Completed => [self::Settled],
            self::Settled, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }
}
