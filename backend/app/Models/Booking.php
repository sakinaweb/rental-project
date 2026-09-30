<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, SoftDeletes;

    /** Statuses that hold the vehicle (MD §6). Drafts only until they expire. */
    public const BLOCKING_STATUSES = ['draft', 'pending_payment', 'confirmed', 'picked_up'];

    protected $fillable = [
        'user_id', 'vehicle_id', 'pickup_location_id', 'dropoff_location_id', 'coupon_id', 'start_at', 'end_at', 'status',
        'currency_code', 'exchange_rate', 'subtotal', 'tax_total', 'discount_total', 'deposit_amount', 'grand_total',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'completed_at' => 'datetime',
        'subtotal' => 'integer',
        'tax_total' => 'integer',
        'discount_total' => 'integer',
        'deposit_amount' => 'integer',
        'grand_total' => 'integer',
    ];

    /** Unpaid drafts stop holding the vehicle after this many minutes (settings, default 15). */
    public static function draftTtlMinutes(): int
    {
        return (int) Setting::valueOf('booking.draft_ttl_minutes', 15);
    }

    public function isExpiredDraft(): bool
    {
        return $this->status === BookingStatus::Draft
            && $this->created_at->lt(now()->subMinutes(self::draftTtlMinutes()));
    }

    /** The only way to change status: rejects transitions MD §6 doesn't allow. */
    public function transitionTo(BookingStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw new DomainException(__('messages.booking_invalid_status'));
        }
        if ($this->isExpiredDraft() && $to !== BookingStatus::Cancelled) {
            throw new DomainException(__('messages.booking_draft_expired'));
        }

        $this->status = $to;
        if ($to === BookingStatus::Completed) {
            $this->completed_at = now();
        }
        $this->save();
    }

    /** Net money held for this booking: succeeded payments minus refunds already returned to the wallet. */
    public function amountPaid(): int
    {
        $paid = $this->payments
            ->where('status', Payment::SUCCEEDED)
            ->where('currency_code', $this->currency_code)
            ->sum('amount');
        $refunded = WalletTransaction::where('reference_type', 'booking')
            ->where('reference_id', $this->id)
            ->where('type', WalletTransaction::REFUND)
            ->where('currency_code', $this->currency_code)
            ->sum('amount');

        return (int) ($paid - $refunded);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return HasMany<BookingAddon, $this> */
    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Deposit, $this> */
    public function deposit(): HasOne
    {
        return $this->hasOne(Deposit::class);
    }

    /** @return HasMany<Damage, $this> */
    public function damages(): HasMany
    {
        return $this->hasMany(Damage::class);
    }

    /**
     * Excludes drafts older than the draft TTL.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeNotExpiredDraft(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('status', '!=', BookingStatus::Draft->value)
            ->orWhere('created_at', '>=', now()->subMinutes(self::draftTtlMinutes())));
    }

    /**
     * Bookings holding the vehicle anywhere in [from, to], widened by the turnaround buffer on both sides.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeBlocking(Builder $query, CarbonInterface $from, CarbonInterface $to, int $bufferMinutes): void
    {
        $query->whereIn('status', self::BLOCKING_STATUSES)
            ->notExpiredDraft()
            ->where('start_at', '<', $to->copy()->addMinutes($bufferMinutes))
            ->where('end_at', '>', $from->copy()->subMinutes($bufferMinutes));
    }
}
