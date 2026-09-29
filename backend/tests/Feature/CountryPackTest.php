<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Setting;
use App\Services\CountryPackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryPackTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function countryId(string $iso): int
    {
        return Country::where('iso_code', $iso)->value('id');
    }

    public function test_every_seeded_country_has_a_pack_config(): void
    {
        foreach (['IN', 'AE', 'US', 'DE'] as $iso) {
            $rules = app(CountryPackService::class)->rulesFor($this->countryId($iso));
            $this->assertArrayHasKey('min_age', $rules, $iso);
            $this->assertArrayHasKey('licence', $rules, $iso);
            $this->assertArrayHasKey('kyc_document_types', $rules, $iso);
            $this->assertArrayHasKey('tax_rules', $rules, $iso);
            $this->assertArrayHasKey('features', $rules, $iso);
        }
    }

    public function test_db_overrides_win_over_config(): void
    {
        $country = Country::where('iso_code', 'AE')->first();
        $country->pack->update(['config_json' => ['min_age' => ['car' => 25]]]);

        $rules = app(CountryPackService::class)->rulesFor($country->id);

        $this->assertSame(25, $rules['min_age']['car']);
        $this->assertSame(18, $rules['min_age']['bike']); // untouched keys keep config values
    }

    public function test_feature_needs_both_global_flag_and_pack_flag(): void
    {
        $in = $this->countryId('IN');
        $features = fn () => app(CountryPackService::class)->rulesFor($in)['features'];

        $this->assertFalse($features()['partner']); // pack on, global off

        Setting::updateOrCreate(['key' => 'features.partner'], ['value_json' => true]);
        $this->assertTrue($features()['partner']);

        Setting::updateOrCreate(['key' => 'features.investor'], ['value_json' => true]);
        $this->assertFalse($features()['investor']); // global on, pack off

        Country::find($in)->pack->update(['config_json' => ['features' => ['partner' => false]]]);
        $this->assertFalse($features()['partner']);
    }
}
