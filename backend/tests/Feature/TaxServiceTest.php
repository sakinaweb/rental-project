<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\TaxRule;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private int $countryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->countryId = Country::where('iso_code', 'AE')->value('id');
    }

    private function rule(float $rate, bool $inclusive, string $appliesTo = 'all'): void
    {
        TaxRule::create(['country_id' => $this->countryId, 'name' => 'VAT', 'rate_pct' => $rate, 'applies_to' => $appliesTo, 'inclusive' => $inclusive]);
    }

    /** @return array{net: int, tax: int, gross: int} */
    private function apply(int $amount, string $appliesTo = 'all'): array
    {
        return app(TaxService::class)->apply($amount, $this->countryId, $appliesTo);
    }

    public function test_no_rules_means_no_tax(): void
    {
        $this->assertSame(['net' => 1000, 'tax' => 0, 'gross' => 1000], $this->apply(1000));
    }

    public function test_exclusive_is_added_on_top(): void
    {
        $this->rule(5, inclusive: false);

        $this->assertSame(['net' => 1000, 'tax' => 50, 'gross' => 1050], $this->apply(1000));
    }

    public function test_inclusive_is_extracted_and_rounded(): void
    {
        $this->rule(5, inclusive: true);

        // 1000 / 1.05 = 952.38 -> 952
        $this->assertSame(['net' => 952, 'tax' => 48, 'gross' => 1000], $this->apply(1000));
    }

    public function test_mixed_inclusive_and_exclusive(): void
    {
        $this->rule(5, inclusive: true);
        $this->rule(2, inclusive: false);

        // net 952; exclusive 2% of 952 = 19.04 -> 19
        $this->assertSame(['net' => 952, 'tax' => 67, 'gross' => 1019], $this->apply(1000));
    }

    public function test_applies_to_filters_by_vehicle_type_and_other_countries_are_ignored(): void
    {
        $this->rule(5, inclusive: false);
        $this->rule(10, inclusive: false, appliesTo: 'bike');
        TaxRule::create(['country_id' => Country::where('iso_code', 'IN')->value('id'), 'name' => 'GST', 'rate_pct' => 18, 'applies_to' => 'all', 'inclusive' => false]);

        $this->assertSame(150, $this->apply(1000, 'bike')['tax']);
        $this->assertSame(50, $this->apply(1000, 'car')['tax']);
    }

    public function test_only_rules_named_by_the_country_pack_apply(): void
    {
        $this->rule(5, inclusive: false); // 'VAT', referenced by config/countrypacks/ae.php
        TaxRule::create(['country_id' => $this->countryId, 'name' => 'Tourism fee', 'rate_pct' => 10, 'applies_to' => 'all', 'inclusive' => false]);

        $this->assertSame(50, $this->apply(1000)['tax']);
    }

    public function test_pack_override_can_reference_several_rules_or_none(): void
    {
        $this->rule(5, inclusive: false);
        TaxRule::create(['country_id' => $this->countryId, 'name' => 'Tourism fee', 'rate_pct' => 10, 'applies_to' => 'all', 'inclusive' => false]);
        $pack = Country::find($this->countryId)->pack;

        $pack->update(['config_json' => ['tax_rules' => ['VAT', 'Tourism fee']]]);
        $this->assertSame(150, $this->apply(1000)['tax']);

        $pack->update(['config_json' => ['tax_rules' => 'Tourism fee']]);
        $this->assertSame(100, $this->apply(1000)['tax']);

        $pack->update(['config_json' => ['tax_rules' => []]]);
        $this->assertSame(0, $this->apply(1000)['tax']);
    }
}
