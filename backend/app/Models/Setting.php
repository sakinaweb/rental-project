<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value_json'];

    protected $casts = ['value_json' => 'json'];

    public static function valueOf(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->first()->value_json ?? $default;
    }
}
