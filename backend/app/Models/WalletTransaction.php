<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Append-only wallet ledger. One running balance per user and currency (balance_after of the latest row).
class WalletTransaction extends Model
{
    public const TOPUP = 'topup';             // + gateway top-up

    public const PAYMENT = 'payment';         // - booking paid from wallet

    public const REFUND = 'refund';           // + cancelled booking, or a payment that arrived after cancellation

    public const DEPOSIT_RELEASE = 'deposit_release'; // + deposit returned at settlement, minus approved damages

    protected $fillable = ['user_id', 'type', 'amount', 'currency_code', 'balance_after', 'reference_type', 'reference_id'];

    protected $casts = ['amount' => 'integer', 'balance_after' => 'integer'];
}
