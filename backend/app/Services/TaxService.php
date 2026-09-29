<?php

namespace App\Services;

use App\Models\TaxRule;

class TaxService
{
    /**
     * Applies the country's tax rules to an amount in minor units (MD §5).
     * Inclusive rules are extracted from the amount; exclusive rules are added on top of the net.
     *
     * @param  string  $appliesTo  vehicle category type (bike|car); rules marked 'all' always apply
     * @return array{net: int, tax: int, gross: int}
     */
    public function apply(int $amount, int $countryId, string $appliesTo = 'all'): array
    {
        $rules = TaxRule::where('country_id', $countryId)->whereIn('applies_to', ['all', $appliesTo])->get();

        $inclusiveRate = $rules->where('inclusive', true)->sum('rate_pct') / 100;
        $exclusiveRate = $rules->where('inclusive', false)->sum('rate_pct') / 100;

        $net = (int) round($amount / (1 + $inclusiveRate));
        $tax = ($amount - $net) + (int) round($net * $exclusiveRate);

        return ['net' => $net, 'tax' => $tax, 'gross' => $net + $tax];
    }
}
