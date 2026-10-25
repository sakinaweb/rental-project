<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Part of an investment placed on one company vehicle. The vehicle's investors split their share by these amounts.
class InvestmentVehicleAllocation extends Model
{
    protected $fillable = ['investment_id', 'vehicle_id', 'allocated_amount'];

    protected $casts = ['allocated_amount' => 'integer'];

    /** @return BelongsTo<Investment, $this> */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }
}
