<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A period the owner keeps a vehicle off the market (MD §4 POST /partner/vehicles/{id}/blockout).
class VehicleBlockout extends Model
{
    protected $fillable = ['vehicle_id', 'start_at', 'end_at', 'reason'];

    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @param  Builder<VehicleBlockout>  $query */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('start_at', '<', $to)->where('end_at', '>', $from);
    }
}
