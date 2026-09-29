<?php

namespace App\Models;

use Database\Factories\VehicleCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleCategory extends Model
{
    /** @use HasFactory<VehicleCategoryFactory> */
    use HasFactory, SoftDeletes;

    public const TYPES = ['bike', 'car'];

    protected $fillable = ['name', 'type'];
}
