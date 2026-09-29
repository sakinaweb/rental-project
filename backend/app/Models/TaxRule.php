<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRule extends Model
{
    use SoftDeletes;

    protected $fillable = ['country_id', 'name', 'rate_pct', 'applies_to', 'inclusive'];

    protected $casts = ['rate_pct' => 'float', 'inclusive' => 'boolean'];
}
