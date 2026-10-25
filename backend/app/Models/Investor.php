<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Someone funding company vehicles for a revenue share (MD §7.11). Like partners, this record (not the user's role)
// gives access, so an investor can still rent. Owner decision: may invest only with approved KYC and the current
// risk disclosure acknowledged.
class Investor extends Model
{
    use SoftDeletes;

    public const KYC_PENDING = 'pending';

    public const KYC_APPROVED = 'approved';

    public const KYC_REJECTED = 'rejected';

    protected $fillable = ['user_id', 'kyc_status', 'risk_ack_at', 'payout_details_json'];

    protected $casts = ['risk_ack_at' => 'datetime', 'payout_details_json' => 'array'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Investment, $this> */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /** @return HasMany<ComplianceDisclosure, $this> */
    public function disclosures(): HasMany
    {
        return $this->hasMany(ComplianceDisclosure::class);
    }

    /** @return HasMany<LedgerAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class, 'owner_id')->where('owner_type', LedgerAccount::INVESTOR);
    }

    public function isApproved(): bool
    {
        return $this->kyc_status === self::KYC_APPROVED;
    }

    public function acknowledged(string $version): bool
    {
        return $this->disclosures()->where('version', $version)->whereNotNull('acknowledged_at')->exists();
    }
}
