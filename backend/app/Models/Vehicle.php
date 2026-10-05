<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_AVAILABLE = 'available';

    public const STATUSES = [self::STATUS_AVAILABLE, 'maintenance', 'retired'];

    public const OWNER_TYPES = ['company', 'partner'];

    protected $fillable = ['model_id', 'branch_id', 'owner_type', 'owner_id', 'plate_no', 'status', 'odometer'];

    /** @return BelongsTo<VehicleModel, $this> */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasOne<PartnerVehicle, $this> partner listing terms (owner_type partner) */
    public function partnerVehicle(): HasOne
    {
        return $this->hasOne(PartnerVehicle::class);
    }

    /** @return HasMany<VehicleBlockout, $this> */
    public function blockouts(): HasMany
    {
        return $this->hasMany(VehicleBlockout::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Vehicles customers can see and book: in service, at a branch in an active country, and — for a partner's
     * vehicle — approved by the admin with the partner active (Phase 9).
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', self::STATUS_AVAILABLE)
            ->whereHas('branch.location.city.country', fn (Builder $q) => $q->where('is_active', true))
            ->where(fn (Builder $q) => $q->where('owner_type', 'company')->orWhereHas('partnerVehicle', fn (Builder $p) => $p
                ->where('status', PartnerVehicle::ACTIVE)
                ->whereHas('partner', fn (Builder $o) => $o->where('status', Partner::ACTIVE))));
    }
}
