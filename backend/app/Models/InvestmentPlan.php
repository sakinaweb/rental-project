<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// What investors can sign up for: one market (country → currency), optionally one vehicle category, a minimum amount
// (minor units), a term, and the share of each allocated vehicle's rental net of tax paid to its investors.
class InvestmentPlan extends Model
{
    use SoftDeletes;

    public const DRAFT = 'draft';

    public const ACTIVE = 'active'; // open for new investments

    public const CLOSED = 'closed'; // no new investments; running ones continue

    protected $fillable = ['name', 'country_id', 'vehicle_category_id', 'min_amount', 'term_months', 'revenue_share_pct', 'status'];

    protected $casts = ['min_amount' => 'integer', 'term_months' => 'integer', 'revenue_share_pct' => 'float'];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<VehicleCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    /** The plan's currency is its country's (also read by MoneyColumn as currency_code). */
    public function getCurrencyCodeAttribute(): string
    {
        return $this->country->currency_code;
    }
}
