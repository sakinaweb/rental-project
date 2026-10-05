<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// pending → batched (in a payout batch) → paid; pending → rejected; a cancelled batch returns its requests to pending.
class PayoutRequest extends Model
{
    public const PENDING = 'pending';

    public const BATCHED = 'batched';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    /** Requests whose money is promised, so it can't be requested again. */
    public const OPEN = [self::PENDING, self::BATCHED];

    protected $fillable = ['account_id', 'payout_batch_id', 'amount', 'status', 'requested_at', 'processed_at'];

    protected $casts = ['amount' => 'integer', 'requested_at' => 'datetime', 'processed_at' => 'datetime'];

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    /** @return BelongsTo<PayoutBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    public function getCurrencyCodeAttribute(): string
    {
        return $this->account->currency_code;
    }
}
