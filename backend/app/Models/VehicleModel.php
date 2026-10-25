<?php

namespace App\Models;

use Database\Factories\VehicleModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class VehicleModel extends Model
{
    /** @use HasFactory<VehicleModelFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['brand_id', 'name', 'category_id', 'seats', 'transmission', 'fuel_type', 'image_path'];

    /** Disk for model photos, same as the other public uploads (INSPECTIONS_DISK, public by default). */
    public static function imageDisk(): string
    {
        return Inspection::disk();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(self::imageDisk())->url($this->image_path) : null;
    }

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
