<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// The investor accepted this version of the risk disclosure at acknowledged_at (kept as evidence; a new version asks again).
class ComplianceDisclosure extends Model
{
    protected $fillable = ['investor_id', 'version', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];
}
