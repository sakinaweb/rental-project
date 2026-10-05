<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// A partner's vehicle and the platform's commission on it. Bookable only when active and the partner is active.
class PartnerVehicle extends Model
{
    use SoftDeletes;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    protected $fillable = ['partner_id', 'vehicle_id', 'commission_pct', 'status'];

    protected $casts = ['commission_pct' => 'float'];

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
