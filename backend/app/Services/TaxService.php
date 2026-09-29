<?php

namespace App\Services;

use App\Models\TaxRule;

class TaxService
{
    public function __construct(private CountryPackService $packs) {}

    /**
     * Applies the country pack's tax rules to an amount in minor units (MD §5).
     * The pack's `tax_rules` value names which of the country's tax_rules rows apply (one name or a list);
     * a pack without it applies no tax. Inclusive rules are extracted from the amount; exclusive rules are
     * added on top of the net.
     *
     * @param  string  $appliesTo  vehicle category type (bike|car); rules marked 'all' always apply
     * @return array{net: int, tax: int, gross: int}
     */
    public function apply(int $amount, int $countryId, string $appliesTo = 'all'): array
    {
        $rules = TaxRule::where('country_id', $countryId)
            ->whereIn('name', (array) ($this->packs->rulesFor($countryId)['tax_rules'] ?? []))
            ->whereIn('applies_to', ['all', $appliesTo])
            ->get();

        $inclusiveRate = $rules->where('inclusive', true)->sum('rate_pct') / 100;
        $exclusiveRate = $rules->where('inclusive', false)->sum('rate_pct') / 100;

        $net = (int) round($amount / (1 + $inclusiveRate));
        $tax = ($amount - $net) + (int) round($net * $exclusiveRate);

        return ['net' => $net, 'tax' => $tax, 'gross' => $net + $tax];
    }
}
