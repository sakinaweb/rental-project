<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\PricingRule;
use App\Models\TaxRule;
use App\Models\Vehicle;
use App\Services\PricingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// MD §8: "Pricing service: every discount/tax/multiplier combination".
// 2026-10-05 is a Monday; 2026-10-09 a Friday. All amounts are minor units.
class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 00:00:00');
        $this->vehicle = Vehicle::factory()->create(); // Mumbai (IN), category type car
    }

    /** @param array<string, mixed> $attrs */
    private function rule(array $attrs = []): PricingRule
    {
        return PricingRule::create($attrs + ['scope_type' => 'global', 'scope_id' => null, 'unit' => 'day', 'base_rate' => 1000]);
    }

    private function tax(float $rate, bool $inclusive, string $appliesTo = 'all'): void
    {
        TaxRule::create(['country_id' => $this->vehicle->branch->location->city->country_id, 'name' => 'T',
            'rate_pct' => $rate, 'applies_to' => $appliesTo, 'inclusive' => $inclusive]);
    }

    /**
     * @param  array<int, int>  $addons
     * @return array<string, int|string>
     */
    private function quote(string $from, string $to, array $addons = [], ?Coupon $coupon = null): array
    {
        return app(PricingService::class)->quote($this->vehicle, Carbon::parse($from), Carbon::parse($to), $addons, $coupon);
    }

    public function test_weekdays_at_base_rate_in_country_currency(): void
    {
        $this->rule();

        $q = $this->quote('2026-10-05 10:00', '2026-10-08 10:00');

        $this->assertSame(['INR', 'day', 3, 3000, 3000], [$q['currency_code'], $q['unit'], $q['units'], $q['rental'], $q['grand_total']]);
    }

    public function test_started_day_counts_as_a_full_day(): void
    {
        $this->rule();

        $this->assertSame(2, $this->quote('2026-10-05 10:00', '2026-10-06 11:00')['units']);
        $this->assertSame(1, $this->quote('2026-10-05 10:00', '2026-10-05 10:30')['units']);
    }

    public function test_weekend_multiplier_applies_to_units_starting_on_weekend(): void
    {
        $this->rule(['weekend_multiplier' => 1.5]);

        // Fri, Sat, Sun
        $this->assertSame(1000 + 1500 + 1500, $this->quote('2026-10-09 10:00', '2026-10-12 10:00')['rental']);
    }

    public function test_hourly_weekend_multiplier_is_per_hour(): void
    {
        $this->rule(['unit' => 'hour', 'base_rate' => 100, 'weekend_multiplier' => 1.5]);

        // Fri 22:00, 23:00, Sat 00:00, 01:00
        $this->assertSame(100 + 100 + 150 + 150, $this->quote('2026-10-09 22:00', '2026-10-10 02:00')['rental']);
    }

    public function test_customer_gets_the_cheaper_of_hourly_and_daily(): void
    {
        $this->rule(['unit' => 'hour', 'base_rate' => 100]);
        $this->rule(['unit' => 'day', 'base_rate' => 1000]);

        $this->assertSame(['hour', 500], array_values(array_intersect_key($this->quote('2026-10-05 10:00', '2026-10-05 15:00'), ['unit' => 0, 'rental' => 0])));
        $this->assertSame(['day', 1000], array_values(array_intersect_key($this->quote('2026-10-05 10:00', '2026-10-06 09:00'), ['unit' => 0, 'rental' => 0])));
        $this->assertSame(['day', 2000], array_values(array_intersect_key($this->quote('2026-10-05 10:00', '2026-10-06 16:00'), ['unit' => 0, 'rental' => 0])));
    }

    public function test_monthly_discount_from_30_days(): void
    {
        $this->rule(['weekend_multiplier' => 1.5, 'monthly_discount_pct' => 10]);

        $q = $this->quote('2026-10-05 10:00', '2026-11-04 10:00'); // 30 days, 8 weekend days
        $this->assertSame(22 * 1000 + 8 * 1500, $q['rental']);
        $this->assertSame(3400, $q['long_rental_discount']);
        $this->assertSame(30600, $q['grand_total']);

        $this->assertSame(0, $this->quote('2026-10-05 10:00', '2026-11-03 10:00')['long_rental_discount']); // 29 days
    }

    public function test_most_specific_scope_wins(): void
    {
        $v = $this->vehicle;
        $scopes = [
            ['vehicle', $v->id, 600], ['model', $v->model_id, 700], ['category', $v->vehicleModel->category_id, 800],
            ['branch', $v->branch_id, 900], ['global', null, 1000],
        ];
        foreach ($scopes as [$type, $id, $rate]) {
            $this->rule(['scope_type' => $type, 'scope_id' => $id, 'base_rate' => $rate]);
        }

        foreach ($scopes as [$type, , $rate]) {
            $this->assertSame($rate, $this->quote('2026-10-05 10:00', '2026-10-06 10:00')['rental'], $type);
            PricingRule::where('scope_type', $type)->delete(); // soft delete: next scope takes over
        }
    }

    public function test_addons_per_day_and_per_booking(): void
    {
        $this->rule();
        $helmet = Addon::create(['name' => 'Helmet', 'price' => 200, 'type' => Addon::PER_DAY]);
        $delivery = Addon::create(['name' => 'Delivery', 'price' => 500, 'type' => Addon::PER_BOOKING]);

        $q = $this->quote('2026-10-05 10:00', '2026-10-08 10:00', [$helmet->id => 2, $delivery->id => 1]);

        $this->assertSame(200 * 2 * 3 + 500, $q['addons_total']);
        $this->assertSame(3000 + 1700, $q['subtotal']);
    }

    public function test_coupons_fixed_percent_and_capped(): void
    {
        $this->rule();
        $fixed = Coupon::create(['code' => 'F', 'type' => Coupon::FIXED, 'value' => 250]);
        $percent = Coupon::create(['code' => 'P', 'type' => Coupon::PERCENT, 'value' => 1250]);
        $huge = Coupon::create(['code' => 'H', 'type' => Coupon::FIXED, 'value' => 999999]);

        $this->assertSame(250, $this->quote('2026-10-05 10:00', '2026-10-07 10:00', [], $fixed)['coupon_discount']);
        $this->assertSame(250, $this->quote('2026-10-05 10:00', '2026-10-07 10:00', [], $percent)['coupon_discount']); // 12.5% of 2000
        $capped = $this->quote('2026-10-05 10:00', '2026-10-07 10:00', [], $huge);
        $this->assertSame([2000, 0], [$capped['coupon_discount'], $capped['grand_total']]);
    }

    public function test_coupon_outside_validity_window_is_rejected(): void
    {
        $this->rule();
        $expired = Coupon::create(['code' => 'OLD', 'type' => Coupon::FIXED, 'value' => 100, 'valid_to' => '2026-09-30 23:59:59']);

        $this->expectException(DomainException::class);
        $this->quote('2026-10-05 10:00', '2026-10-06 10:00', [], $expired);
    }

    public function test_full_combination_hand_calculated(): void
    {
        $this->rule(['weekend_multiplier' => 1.5]);
        $perDay = Addon::create(['name' => 'GPS', 'price' => 200, 'type' => Addon::PER_DAY]);
        $once = Addon::create(['name' => 'Delivery', 'price' => 500, 'type' => Addon::PER_BOOKING]);
        $coupon = Coupon::create(['code' => 'TEN', 'type' => Coupon::PERCENT, 'value' => 1000]);
        $this->tax(18, inclusive: false);

        $q = $this->quote('2026-10-09 10:00', '2026-10-12 10:00', [$perDay->id => 1, $once->id => 1], $coupon);

        // rental 4000 + addons 1100 = 5100; coupon 10% = 510; taxable 4590; 18% = 826.2 -> 826
        $this->assertSame(
            ['rental' => 4000, 'addons_total' => 1100, 'subtotal' => 5100, 'coupon_discount' => 510, 'discount_total' => 510, 'tax_total' => 826, 'grand_total' => 5416],
            array_intersect_key($q, array_flip(['rental', 'addons_total', 'subtotal', 'coupon_discount', 'discount_total', 'tax_total', 'grand_total'])),
        );
    }

    /** @return array<string, array{0: bool, 1: bool, 2: ?string, 3: ?string}> */
    public static function combinations(): array
    {
        $cases = [];
        foreach ([false, true] as $weekend) {
            foreach ([false, true] as $monthly) {
                foreach ([null, Coupon::FIXED, Coupon::PERCENT] as $coupon) {
                    foreach ([null, 'inclusive', 'exclusive', 'mixed'] as $tax) {
                        $name = implode('/', [$weekend ? 'weekend' : 'flat', $monthly ? 'monthly' : 'short', $coupon ?? 'no-coupon', $tax ?? 'no-tax']);
                        $cases[$name] = [$weekend, $monthly, $coupon, $tax];
                    }
                }
            }
        }

        return $cases;
    }

    #[DataProvider('combinations')]
    public function test_totals_stay_consistent_for_every_combination(bool $weekend, bool $monthly, ?string $couponType, ?string $tax): void
    {
        $this->rule(['base_rate' => 1333, 'weekend_multiplier' => $weekend ? 1.37 : 1, 'monthly_discount_pct' => $monthly ? 12.5 : 0]);
        $coupon = $couponType ? Coupon::create(['code' => 'C', 'type' => $couponType, 'value' => $couponType === Coupon::FIXED ? 777 : 1750]) : null;
        if ($tax === 'inclusive' || $tax === 'mixed') {
            $this->tax(5, inclusive: true);
        }
        if ($tax === 'exclusive' || $tax === 'mixed') {
            $this->tax(18, inclusive: false);
        }

        $q = $this->quote('2026-10-09 10:00', $monthly ? '2026-11-09 10:00' : '2026-10-12 10:00', [], $coupon);

        $taxable = $q['subtotal'] - $q['discount_total'];
        $this->assertSame($q['long_rental_discount'] + $q['coupon_discount'], $q['discount_total']);
        $this->assertGreaterThanOrEqual(0, $taxable);
        $this->assertSame($monthly, $q['long_rental_discount'] > 0);
        $this->assertSame($couponType !== null, $q['coupon_discount'] > 0);
        $this->assertSame($tax !== null, $q['tax_total'] > 0);
        // Inclusive tax sits inside the price; only exclusive tax is added on top.
        $this->assertSame(in_array($tax, ['exclusive', 'mixed'], true), $q['grand_total'] > $taxable);
        if ($tax === null || $tax === 'inclusive') {
            $this->assertSame($taxable, $q['grand_total']);
        }
    }

    public function test_tax_rules_for_other_vehicle_type_are_ignored(): void
    {
        $this->rule();
        $this->tax(12, inclusive: false, appliesTo: 'bike');
        $this->tax(10, inclusive: false, appliesTo: 'car');

        $this->assertSame(100, $this->quote('2026-10-05 10:00', '2026-10-06 10:00')['tax_total']);
    }

    public function test_currency_follows_the_branch_country(): void
    {
        $this->rule();
        $dubai = Location::factory()->create(['city_id' => City::where('name', 'Dubai')->value('id')]);
        $this->vehicle->branch->update(['location_id' => $dubai->id]);

        $this->assertSame('AED', $this->quote('2026-10-05 10:00', '2026-10-06 10:00')['currency_code']);
    }

    public function test_missing_rule_and_unknown_addon_are_errors(): void
    {
        try {
            $this->quote('2026-10-05 10:00', '2026-10-06 10:00');
            $this->fail('expected missing pricing rule error');
        } catch (DomainException) {
        }

        $this->rule();
        $this->expectException(DomainException::class);
        $this->quote('2026-10-05 10:00', '2026-10-06 10:00', [999999 => 1]);
    }
}
