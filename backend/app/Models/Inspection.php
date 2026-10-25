<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

// Handover (pickup) and return checklist (MD §3 inspections, Phase 10): photos, odometer, fuel level, notes, who did it.
class Inspection extends Model
{
    public const PICKUP = 'pickup';

    public const RETURN = 'return';

    protected $fillable = ['booking_id', 'stage', 'photos_json', 'odometer', 'fuel_level', 'notes', 'inspected_by'];

    protected $casts = ['photos_json' => 'array', 'odometer' => 'integer', 'fuel_level' => 'integer'];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /** Disk for checklist and damage photos: public by default, S3 or any other disk via INSPECTIONS_DISK. */
    public static function disk(): string
    {
        return config('filesystems.inspections_disk');
    }

    /** @return list<string> */
    public function photoUrls(): array
    {
        return array_map(fn (string $path) => Storage::disk(self::disk())->url($path), $this->photos_json ?? []);
    }
}
