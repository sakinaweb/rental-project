<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Owner decision (Phase 9): payouts are manual. The admin batches pending requests of one currency, pays them by bank
// transfer outside the system, then marks the batch paid (which posts the ledger), or cancels it.
class PayoutBatch extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['currency_code', 'processed_by', 'total_amount', 'status', 'processed_at'];

    protected $casts = ['total_amount' => 'integer', 'processed_at' => 'datetime'];

    /** @return HasMany<PayoutRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
