<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PaymentService;
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

Artisan::command('payments:expire', function (PaymentService $payments) {
    $this->info("Cancelled {$payments->expireUnpaid()} unpaid booking(s) past the payment timeout.");
})->purpose('Cancel pending_payment bookings not paid within settings.payment.timeout_minutes');

// Creates (or promotes) an admin who can sign in to the Filament panel at /admin.
Artisan::command('app:create-admin {email} {--name=Admin} {--password=}', function (string $email) {
    $password = $this->option('password') ?: $this->secret('Password (min 8 characters)');
    if (strlen((string) $password) < 8) {
        $this->error('Password must be at least 8 characters.');

        return 1;
    }

    $user = User::withTrashed()->firstOrNew(['email' => $email]);
    $user->forceFill([
        'name' => $user->name ?? $this->option('name'),
        'password' => $password,
        'role_id' => Role::idFor(Role::ADMIN),
        'status' => User::STATUS_ACTIVE,
        'deleted_at' => null,
    ])->save();
    $this->info("Admin ready: {$user->email}");
})->purpose('Create or promote an admin user for the admin panel');

// Runs via `schedule:run` from cron every minute (shared hosting) or `schedule:work` (docker-compose scheduler).
Schedule::command('bookings:settle')->hourly()->withoutOverlapping();
Schedule::command('payments:expire')->everyMinute()->withoutOverlapping();
