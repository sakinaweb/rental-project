<?php

namespace App\Http\Resources;

use App\Models\PricingRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PricingRule */
class PricingRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'unit' => $this->unit,
            'base_rate' => $this->base_rate,
            'weekend_multiplier' => $this->weekend_multiplier,
            'monthly_discount_pct' => $this->monthly_discount_pct,
        ];
    }
}
