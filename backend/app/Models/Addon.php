<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addon extends Model
{
    use SoftDeletes;

    public const PER_DAY = 'per_day';

    public const PER_BOOKING = 'per_booking';

    protected $fillable = ['name', 'price', 'type'];

    protected $casts = ['price' => 'integer'];
}
