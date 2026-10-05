<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    protected $fillable = ['user_id', 'token', 'platform'];
}
