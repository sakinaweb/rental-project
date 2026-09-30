<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// One attempt to collect money: for a booking, or a wallet top-up (booking_id null).
// pending → succeeded | failed | expired. A failed or expired payment can still become succeeded
// if the gateway later reports the money arrived; succeeded is final.
class Payment extends Model
{
    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public const WALLET = 'wallet';

    protected $fillable = ['user_id', 'booking_id', 'gateway', 'gateway_ref', 'amount', 'currency_code', 'status'];

    protected $casts = ['amount' => 'integer'];
}
