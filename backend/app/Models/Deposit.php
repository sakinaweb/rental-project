<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Created as held when a booking is confirmed; released on cancellation (in full) or settlement (minus approved damages).
// Moving the money back to the customer is the payment gateway's job (Phase 4).
class Deposit extends Model
{
    public const HELD = 'held';

    public const RELEASED = 'released';

    protected $fillable = ['booking_id', 'amount', 'status', 'refunded_amount', 'refunded_at'];

    protected $casts = ['amount' => 'integer', 'refunded_amount' => 'integer', 'refunded_at' => 'datetime'];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function release(int $refund): void
    {
        $this->update(['status' => self::RELEASED, 'refunded_amount' => $refund, 'refunded_at' => now()]);
    }
}
