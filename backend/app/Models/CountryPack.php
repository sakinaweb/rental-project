<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryPack extends Model
{
    protected $fillable = ['country_id', 'config_json'];

    protected $casts = ['config_json' => 'array'];
}
