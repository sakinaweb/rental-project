<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    public const FIXED = 'fixed'; // value in minor units

    public const PERCENT = 'percent'; // value in basis points (1250 = 12.5%)

    protected $fillable = ['code', 'type', 'value', 'valid_from', 'valid_to', 'usage_limit'];

    protected $casts = ['value' => 'integer', 'valid_from' => 'datetime', 'valid_to' => 'datetime'];

    // ponytail: usage_limit is enforced at booking creation (Phase 3), once bookings record which coupon they used.
    public function isValidAt(CarbonInterface $at): bool
    {
        return (! $this->valid_from || $at->gte($this->valid_from)) && (! $this->valid_to || $at->lte($this->valid_to));
    }
}
