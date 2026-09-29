<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\BookingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// MD §6: completed → (damage hold window) → settled. Bookings with damages still under review are skipped.
Artisan::command('bookings:settle', function (BookingService $bookings) {
    $due = Booking::where('status', BookingStatus::Completed->value)
        ->where('completed_at', '<=', now()->subHours((int) Setting::valueOf('booking.damage_hold_hours', 72)))
        ->get();

    $settled = 0;
    foreach ($due as $booking) {
        try {
            $bookings->settle($booking);
            $settled++;
        } catch (DomainException $e) {
            $this->warn("Booking {$booking->id}: {$e->getMessage()}");
        }
    }
    $this->info("Settled {$settled} of {$due->count()} due bookings.");
})->purpose('Settle completed bookings whose damage hold window has passed');

// Runs via `schedule:run` from cron every minute (shared hosting) or `schedule:work` (docker-compose scheduler).
Schedule::command('bookings:settle')->hourly()->withoutOverlapping();
