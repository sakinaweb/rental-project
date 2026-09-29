<?php

namespace App\Http\Requests;

use App\Models\PricingRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PricingRuleRequest extends FormRequest
{
    private const SCOPE_TABLES = [
        'vehicle' => 'vehicles',
        'model' => 'vehicle_models',
        'category' => 'vehicle_categories',
        'branch' => 'branches',
    ];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $scope = $this->input('scope_type');
        $table = self::SCOPE_TABLES[$scope] ?? null;

        return [
            'scope_type' => ['required', Rule::in(PricingRule::SCOPES)],
            'scope_id' => $table
                ? ['required', 'integer', Rule::exists($table, 'id')->whereNull('deleted_at')]
                : ['prohibited'],
            // One rule per scope and unit, so the price is never ambiguous.
            'unit' => ['required', Rule::in(PricingRule::UNITS), Rule::unique('pricing_rules')
                ->where('scope_type', $scope)
                ->where('scope_id', $table ? $this->integer('scope_id') : null)
                ->whereNull('deleted_at')],
            'base_rate' => ['required', 'integer', 'min:1'],
            'weekend_multiplier' => ['sometimes', 'numeric', 'min:0.01', 'max:999.99'],
            'monthly_discount_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
