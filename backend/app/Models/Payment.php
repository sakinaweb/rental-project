<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Written by the payment gateways and webhooks in Phase 4; Phase 3 only reads succeeded payments to confirm bookings.
class Payment extends Model
{
    public const SUCCEEDED = 'succeeded';

    protected $fillable = ['booking_id', 'gateway', 'gateway_ref', 'amount', 'currency_code', 'status'];

    protected $casts = ['amount' => 'integer'];
}
