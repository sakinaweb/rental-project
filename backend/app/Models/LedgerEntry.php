<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// earnings_ledger (MD §3): append-only, signed amounts, running balance per account. Written only by LedgerService.
class LedgerEntry extends Model
{
    public const CREDIT_SHARE = 'credit_share';

    public const DEBIT_DEDUCTION = 'debit_deduction';

    public const PAYOUT = 'payout';

    public const REVERSAL = 'reversal';

    public const UPDATED_AT = null;

    protected $table = 'earnings_ledger';

    protected $fillable = ['account_id', 'booking_id', 'entry_type', 'amount', 'running_balance'];

    protected $casts = ['amount' => 'integer', 'running_balance' => 'integer'];

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getCurrencyCodeAttribute(): string
    {
        return $this->account->currency_code;
    }
}
