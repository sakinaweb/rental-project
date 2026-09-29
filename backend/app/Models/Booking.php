<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Phase 2 only needs bookings for availability; creation and the state machine come in Phase 3.
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, SoftDeletes;

    /** Statuses that hold the vehicle (MD §6). */
    public const BLOCKING_STATUSES = ['draft', 'pending_payment', 'confirmed', 'picked_up'];

    protected $fillable = [
        'user_id', 'vehicle_id', 'pickup_location_id', 'dropoff_location_id', 'start_at', 'end_at', 'status',
        'currency_code', 'exchange_rate', 'subtotal', 'tax_total', 'discount_total', 'deposit_amount', 'grand_total',
    ];

    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];

    /**
     * Bookings holding the vehicle anywhere in [from, to], widened by the turnaround buffer on both sides.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeBlocking(Builder $query, CarbonInterface $from, CarbonInterface $to, int $bufferMinutes): void
    {
        $query->whereIn('status', self::BLOCKING_STATUSES)
            ->where('start_at', '<', $to->copy()->addMinutes($bufferMinutes))
            ->where('end_at', '>', $from->copy()->subMinutes($bufferMinutes));
    }
}
