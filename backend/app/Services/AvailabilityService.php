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
     */
    public function isAvailable(int $vehicleId, CarbonInterface $from, CarbonInterface $to): bool
    {
        $vehicle = Vehicle::whereKey($vehicleId)->lockForUpdate()->first();

        if (! $vehicle || $vehicle->status !== Vehicle::STATUS_AVAILABLE) {
            return false;
        }

        return ! $vehicle->bookings()->blocking($from, $to, $this->bufferMinutes())->exists();
    }

    /** Turnaround time kept free before and after every booking. Admin-editable in settings. */
    public function bufferMinutes(): int
    {
        return (int) Setting::valueOf('availability.buffer_minutes', 60);
    }
}
