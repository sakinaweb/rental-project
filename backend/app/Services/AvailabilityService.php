<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Vehicle;
use Carbon\CarbonInterface;

class AvailabilityService
{
    /**
     * Row-locked check (MD §5). Locks the vehicle row, so two bookings for the same vehicle serialize.
     * Call it inside the DB transaction that creates the booking; the lock is held until that commits.
     * The bookings read is a locking read too, so it sees rows committed after the transaction began.
     *
     * @param  int|null  $ignoreBookingId  a booking being extended, which must not block itself
     */
    public function isAvailable(int $vehicleId, CarbonInterface $from, CarbonInterface $to, ?int $ignoreBookingId = null): bool
    {
        // listed(): in service, active country, and for a partner's vehicle approved with the partner active (Phase 9).
        $vehicle = Vehicle::listed()->whereKey($vehicleId)->lockForUpdate()->first();

        if (! $vehicle || $vehicle->blockouts()->overlapping($from, $to)->exists()) {
            return false;
        }

        return ! $vehicle->bookings()
            ->blocking($from, $to, $this->bufferMinutes())
            ->when($ignoreBookingId, fn ($q, $id) => $q->whereKeyNot($id))
            ->sharedLock()
            ->exists();
    }

    /** Turnaround time kept free before and after every booking. Admin-editable in settings. */
    public function bufferMinutes(): int
    {
        return (int) Setting::valueOf('availability.buffer_minutes', 60);
    }
}
