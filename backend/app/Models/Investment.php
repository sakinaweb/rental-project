<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Owner decision (Phase 11): pending until the admin confirms the bank transfer and allocates it to company vehicles
// (active, the term starts); earns on bookings that start within the term; closed once the principal is paid back.
class Investment extends Model
{
    use SoftDeletes;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['investor_id', 'plan_id', 'amount', 'status', 'started_at', 'ends_at'];

    protected $casts = ['amount' => 'integer', 'started_at' => 'datetime', 'ends_at' => 'datetime'];

    /** @return BelongsTo<Investor, $this> */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    /** @return BelongsTo<InvestmentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InvestmentPlan::class, 'plan_id')->withTrashed();
    }

    /** The plan's currency (read by MoneyColumn). */
    public function getCurrencyCodeAttribute(): string
    {
        return $this->plan->currency_code;
    }

    /** @return HasMany<InvestmentVehicleAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvestmentVehicleAllocation::class);
    }
}
