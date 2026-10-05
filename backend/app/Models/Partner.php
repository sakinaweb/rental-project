<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// A vehicle owner who lists cars/bikes on the platform (MD §7.9). Applies as pending; the admin approves or suspends.
// Partner access is this record, not the user's role: a partner can still rent as a customer.
class Partner extends Model
{
    use SoftDeletes;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const REJECTED = 'rejected';

    protected $fillable = ['user_id', 'payout_details_json', 'status'];

    protected $casts = ['payout_details_json' => 'array'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PartnerVehicle, $this> */
    public function partnerVehicles(): HasMany
    {
        return $this->hasMany(PartnerVehicle::class);
    }

    /** @return HasMany<LedgerAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class, 'owner_id')->where('owner_type', LedgerAccount::PARTNER);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
