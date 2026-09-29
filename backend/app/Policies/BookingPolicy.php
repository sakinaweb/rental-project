<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;

// Customers see and act on their own bookings only; admins on all.
class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id || $user->hasRole(Role::ADMIN);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }
}
