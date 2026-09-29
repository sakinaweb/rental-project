<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAddon extends Model
{
    protected $fillable = ['booking_id', 'addon_id', 'qty', 'price']; // price: unit price at booking time

    protected $casts = ['qty' => 'integer', 'price' => 'integer'];

    /** @return BelongsTo<Addon, $this> */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class)->withTrashed();
    }
}
