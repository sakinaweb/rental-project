<?php

namespace App\Models;

use Database\Factories\VehicleModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleModel extends Model
{
    /** @use HasFactory<VehicleModelFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['brand_id', 'name', 'category_id', 'seats', 'transmission', 'fuel_type'];

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<VehicleCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class);
    }
}
