<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// One balance per owner and currency (MD §3). The platform's accounts use owner_id 0, so the unique index
// (owner_type, owner_id, currency_code) also holds for them (NULL would allow duplicates).
class LedgerAccount extends Model
{
    public const PLATFORM = 'platform';

    public const PARTNER = 'partner';

    public const INVESTOR = 'investor';

    protected $fillable = ['owner_type', 'owner_id', 'currency_code', 'balance'];

    protected $casts = ['owner_id' => 'integer', 'balance' => 'integer'];

    /** "Platform", or the partner's / investor's name, for admin lists. */
    public function ownerLabel(): string
    {
        $owner = match ($this->owner_type) {
            self::PARTNER => Partner::withTrashed()->with('user')->find($this->owner_id),
            self::INVESTOR => Investor::withTrashed()->with('user')->find($this->owner_id),
            default => null,
        };

        return $this->owner_type === self::PLATFORM ? 'Platform' : ucfirst($this->owner_type).' '.($owner?->user->name ?? "#{$this->owner_id}");
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    /** @return HasMany<PayoutRequest, $this> */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class, 'account_id');
    }
}
