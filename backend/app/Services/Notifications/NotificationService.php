<?php

namespace App\Services\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MD §5 NotificationService. Phase 7 covers push to the customer app (owner decision); SMS already has its own
 * driver (OTP) and email can join here later. Sent after the booking change is committed; a failed push never
 * affects the booking.
 */
class NotificationService
{
    public function __construct(private PushDriver $push) {}

    public function bookingStatusChanged(Booking $booking): void
    {
        $key = match ($booking->status) {
            BookingStatus::Confirmed => 'messages.push_booking_confirmed',
            BookingStatus::Cancelled => 'messages.push_booking_cancelled',
            default => null,
        };
        if (! $key) {
            return;
        }

        $user = $booking->user;
        foreach (DeviceToken::where('user_id', $user->id)->get() as $device) {
            try {
                $delivered = $this->push->send($device->token, config('app.name'), __($key, ['id' => $booking->id], $user->locale), [
                    'type' => 'booking', 'booking_id' => (string) $booking->id, 'status' => $booking->status->value,
                ]);
                if (! $delivered) {
                    $device->delete();
                }
            } catch (Throwable $e) {
                Log::warning('Push failed', ['device_id' => $device->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
