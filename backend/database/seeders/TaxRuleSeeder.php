<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TaxRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

// Default tax rules for the four country packs (Phase 8, owner decision). Run once per install:
// php artisan db:seed --class=TaxRuleSeeder. Idempotent: never changes or restores a rule the admin already has.
// Kept out of DatabaseSeeder so tests choose their own rates.
class TaxRuleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $rules = [
            // iso => [name (the pack's tax_rules), rate %, included in prices?]
            'IN' => ['GST', 18, false],
            'AE' => ['VAT', 5, true],
            'DE' => ['MwSt', 19, true],
            'US' => ['Sales tax', 0, false], // one country-wide rate; the admin sets it for the main market
        ];

        foreach ($rules as $iso => [$name, $rate, $inclusive]) {
            $countryId = Country::where('iso_code', $iso)->value('id');
            if ($countryId) {
                TaxRule::withTrashed()->firstOrCreate( // a rule the admin deleted stays deleted
                    ['country_id' => $countryId, 'name' => $name, 'applies_to' => 'all'],
                    ['rate_pct' => $rate, 'inclusive' => $inclusive],
                );
            }
        }
    }
}
