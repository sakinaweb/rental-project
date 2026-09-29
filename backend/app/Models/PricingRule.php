<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingRule extends Model
{
    use SoftDeletes;

    /** Most specific first: the first scope with any rule wins. */
    public const SCOPES = ['vehicle', 'model', 'category', 'branch', 'global'];

    public const UNITS = ['hour', 'day'];

    protected $fillable = ['scope_type', 'scope_id', 'unit', 'base_rate', 'weekend_multiplier', 'monthly_discount_pct'];

    protected $casts = ['base_rate' => 'integer', 'weekend_multiplier' => 'float', 'monthly_discount_pct' => 'float'];
}
