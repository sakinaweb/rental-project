<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Admin actions (Phase 5, decision D6): booking actions, settings, damages, user role/status changes.
class AuditLog extends Model
{
    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'meta_json'];

    protected $casts = ['meta_json' => 'array'];

    /** @param  array<string, mixed>  $meta */
    public static function record(string $action, ?Model $subject = null, array $meta = []): self
    {
        return static::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta_json' => $meta ?: null,
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
