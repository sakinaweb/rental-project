<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Recorded at return (staff app, Phase 10). Settlement deducts approved damages from the deposit.
class Damage extends Model
{
    public const REPORTED = 'reported'; // awaiting review: blocks settlement

    public const APPROVED = 'approved'; // cost is charged against the deposit

    public const WAIVED = 'waived';

    protected $fillable = ['booking_id', 'description', 'cost', 'evidence_json', 'status'];

    protected $casts = ['cost' => 'integer', 'evidence_json' => 'array'];
}
