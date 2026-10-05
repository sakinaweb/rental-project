<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Location;
use App\Models\PricingRule;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingService;
use App\Services\CountryPackService;
use App\Services\PaymentService;
use App\Services\TaxService;
use App\Support\Money;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

// Phase 8 (MD §7.8): the four country packs with their market defaults — currency, tax, deposit, gateways, RTL.
class CountryPackMarketsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const CITY = ['IN' => 'Mumbai', 'AE' => 'Dubai', 'US' => 'New York', 'DE' => 'Berlin'];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 00:00:00');
        $this->seed(TaxRuleSeeder::class);
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);
        config([
            'services.stripe' => ['key' => 'pk_test_1', 'secret' => 'sk_test_1', 'webhook_secret' => 'whsec_1'],
            'services.razorpay' => ['key_id' => 'rzp_test_1', 'key_secret' => 'rzp_secret', 'webhook_secret' => 'rzp_whsec'],
        ]);
        Http::fake([
            'api.stripe.com/v1/payment_intents' => fn () => Http::response(['id' => 'pi_'.Str::random(10), 'client_secret' => 'secret']),
            'api.razorpay.com/v1/orders' => fn () => Http::response(['id' => 'order_'.Str::random(10)]),
        ]);
    }

    private function country(string $iso): Country
    {
        return Country::where('iso_code', $iso)->sole();
    }

    /** A 3-day car booking (subtotal 3000 minor units) at a branch in the pack's country. */
    private function booking(string $iso, ?User $user = null): Booking
    {
        $location = Location::factory()->create(['city_id' => City::where('name', self::CITY[$iso])->value('id')]);
        $vehicle = Vehicle::factory()->create(['branch_id' => Branch::factory()->create(['location_id' => $location->id])->id]);

        return app(BookingService::class)->create($user ?? User::factory()->create(), $vehicle, Carbon::parse('2026-10-05 10:00'), Carbon::parse('2026-10-08 10:00'));
    }

    public function test_default_tax_per_pack_inclusive_or_added_on_top(): void
    {
        $expected = [ // 10000 minor units in → [net, tax, gross]
            'IN' => [10000, 1800, 11800], // GST 18% added
            'AE' => [9524, 476, 10000],   // VAT 5% included
            'DE' => [8403, 1597, 10000],  // MwSt 19% included
            'US' => [10000, 0, 10000],    // sales tax: 0% until the admin sets the market's rate
        ];

        foreach ($expected as $iso => [$net, $tax, $gross]) {
            $this->assertSame(['net' => $net, 'tax' => $tax, 'gross' => $gross], app(TaxService::class)->apply(10000, $this->country($iso)->id), $iso);
        }
    }

    public function test_booking_in_each_pack_uses_its_currency_tax_and_deposit(): void
    {
        $expected = [ // iso => [currency, tax, grand total, car deposit]
            'IN' => ['INR', 540, 3540, 500000],
            'AE' => ['AED', 143, 3000, 150000],
            'US' => ['USD', 0, 3000, 50000],
            'DE' => ['EUR', 479, 3000, 50000],
        ];

        foreach ($expected as $iso => [$currency, $tax, $grand, $deposit]) {
            $b = $this->booking($iso);
            $this->assertSame([3000, $currency, $tax, $grand, $deposit], [$b->subtotal, $b->currency_code, $b->tax_total, $b->grand_total, $b->deposit_amount], $iso);
        }
    }

    public function test_us_sales_tax_rate_set_by_the_admin_is_added_on_top(): void
    {
        TaxRule::where('country_id', $this->country('US')->id)->sole()->update(['rate_pct' => 8.875]);

        $b = $this->booking('US');
        $this->assertSame([266, 3266], [$b->tax_total, $b->grand_total]);
    }

    public function test_tax_seeder_never_changes_or_restores_admin_rules(): void
    {
        TaxRule::where('country_id', $this->country('AE')->id)->sole()->update(['rate_pct' => 7]);
        TaxRule::where('country_id', $this->country('DE')->id)->sole()->delete();

        $this->seed(TaxRuleSeeder::class);

        $this->assertSame(7.0, TaxRule::where('country_id', $this->country('AE')->id)->sole()->rate_pct);
        $this->assertSame(0, TaxRule::where('country_id', $this->country('DE')->id)->count());
        $this->assertSame(4, TaxRule::withTrashed()->count());
    }

    public function test_each_pack_offers_only_its_card_gateways(): void
    {
        $payments = app(PaymentService::class);
        $this->assertSame(['razorpay'], $payments->gatewaysFor('INR'));
        foreach (['AED', 'USD', 'EUR'] as $currency) {
            $this->assertSame(['stripe'], $payments->gatewaysFor($currency), $currency);
        }

        $this->getJson('/api/v1/payments/gateways?currency=usd')->assertOk()->assertExactJson(['data' => ['stripe']]);
        $this->getJson('/api/v1/payments/gateways?currency=INR')->assertExactJson(['data' => ['razorpay']]);
        $this->getJson('/api/v1/payments/gateways')->assertExactJson(['data' => ['stripe', 'razorpay']]); // all configured
        $this->getJson('/api/v1/payments/gateways?currency=US')->assertUnprocessable();

        config(['services.razorpay.key_secret' => null]); // allowed by the pack but not configured
        $this->assertSame([], $payments->gatewaysFor('INR'));
    }

    public function test_admin_override_replaces_pack_lists_whole(): void
    {
        $in = $this->country('IN');
        $in->pack->update(['config_json' => ['gateways' => ['stripe', 'razorpay'], 'kyc_document_types' => ['passport']]]);
        $rules = app(CountryPackService::class)->rulesFor($in->id);
        $this->assertSame(['stripe', 'razorpay'], $rules['gateways']);
        $this->assertSame(['passport'], $rules['kyc_document_types']); // not merged by position with the defaults
        $this->assertSame(['bike' => 200000, 'car' => 500000], $rules['deposit']); // keyed sections still merge

        $in->pack->update(['config_json' => ['gateways' => []]]);
        $this->assertSame([], app(PaymentService::class)->gatewaysFor('INR'));
    }

    public function test_payments_refuse_a_gateway_outside_the_pack(): void
    {
        $user = User::factory()->create(['country_id' => $this->country('US')->id]);
        $booking = $this->booking('US', $user);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/payments/intent', ['booking_id' => $booking->id, 'gateway' => 'razorpay'])
            ->assertUnprocessable()->assertJsonPath('message', __('messages.payment_gateway_unavailable'));
        $this->postJson('/api/v1/payments/intent', ['booking_id' => $booking->id, 'gateway' => 'stripe'])
            ->assertCreated()->assertJsonPath('data.payment.currency_code', 'USD');

        $this->postJson('/api/v1/wallet/topup', ['amount' => 5000, 'gateway' => 'razorpay'])->assertUnprocessable(); // USD by default
        $this->postJson('/api/v1/wallet/topup', ['amount' => 5000, 'gateway' => 'razorpay', 'currency_code' => 'INR'])->assertCreated();
    }

    public function test_uae_checkout_in_arabic_is_right_to_left_with_dirham_amounts(): void
    {
        $user = User::factory()->create(['country_id' => $this->country('AE')->id]);
        $booking = $this->booking('AE', $user);
        $this->actingAs($user);
        $this->post('/language', ['locale' => 'ar'])->assertRedirect();

        $html = $this->get(route('checkout', $booking))->assertOk()->getContent();
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString(e(Money::format(153000, 'AED', 'ar')), $html); // amount due: total + deposit
        $this->assertStringContainsString(__('site.checkout.pay_stripe', [], 'ar'), $html);
        $this->assertStringNotContainsString(__('site.checkout.pay_razorpay', [], 'ar'), $html);
    }
}
