<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Vehicles customers can see: in service, at a branch in an active country.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', self::STATUS_AVAILABLE)
            ->whereHas('branch.location.city.country', fn (Builder $q) => $q->where('is_active', true));
    }
}
