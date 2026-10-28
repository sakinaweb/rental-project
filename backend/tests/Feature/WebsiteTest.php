<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Livewire\Account\BookingShow;
use App\Livewire\Account\Profile;
use App\Livewire\Account\Wallet;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Checkout;
use App\Livewire\Confirmation;
use App\Livewire\Search;
use App\Livewire\VehicleShow;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleModel;
use App\Models\WalletTransaction;
use App\Services\BookingService;
use App\Services\Sms\SmsDriver;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

// Phase 6 customer website. Vehicle in Mumbai (INR), 1000/day, car deposit 500000:
// Mon 5 Oct → Thu 8 Oct 2026 = 3000 + deposit 500000 = 503000 due.
class WebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Vehicle $vehicle;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 00:00:00');
        $this->vehicle = $this->vehicleIn('Mumbai', ['name' => 'Swift']);
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);
        $this->customer = User::factory()->create(['country_id' => Country::where('iso_code', 'IN')->value('id')]);
    }

    private function vehicleIn(string $city, array $model = []): Vehicle
    {
        $location = Location::factory()->create(['city_id' => City::where('name', $city)->value('id')]);

        return Vehicle::factory()->create([
            'branch_id' => Branch::factory()->create(['location_id' => $location->id])->id,
            'model_id' => VehicleModel::factory()->create($model)->id,
        ]);
    }

    private function draft(?User $user = null, string $from = '2026-10-05 10:00', string $to = '2026-10-08 10:00'): Booking
    {
        return app(BookingService::class)->create($user ?? $this->customer, $this->vehicle, Carbon::parse($from), Carbon::parse($to));
    }

    private function fund(int $amount, ?User $user = null): void
    {
        app(WalletService::class)->credit(($user ?? $this->customer)->id, $amount, 'INR', WalletTransaction::TOPUP, 'payment', 1);
    }

    // --- Search → results → details -------------------------------------------------------------------------

    public function test_home_search_filters_and_prices(): void
    {
        $bike = VehicleCategory::factory()->create(['name' => 'Scooters', 'type' => 'bike']);
        $scooter = $this->vehicleIn('Mumbai', ['name' => 'Activa', 'category_id' => $bike->id]);
        $dubai = $this->vehicleIn('Dubai', ['name' => 'Patrol']);
        Booking::factory()->create(['vehicle_id' => $scooter->id, 'status' => 'confirmed', 'start_at' => '2026-10-05 12:00', 'end_at' => '2026-10-06 12:00']);

        $this->get('/')->assertOk()->assertSee('Swift')->assertSee('Activa')->assertSee('Patrol');

        $in = Country::where('iso_code', 'IN')->value('id');
        Livewire::test(Search::class)
            ->set('country_id', $in)->assertSee('Swift')->assertSee('Activa')->assertDontSee('Patrol')
            ->set('type', 'bike')->assertSee('Activa')->assertDontSee('Swift')
            ->set('type', null)->set('category', $bike->id)->assertSee('Activa')->assertDontSee('Swift')
            ->set('category', null)->set('from', '2026-10-05T10:00')->set('to', '2026-10-08T10:00')
            ->assertSee('Swift')->assertDontSee('Activa')      // booked in that period
            ->assertSee('₹30.00')                               // real quote: 3 × 1000 minor units
            ->set('to', '2026-10-04T10:00')->assertSee(__('site.search.dates_invalid'));

        Livewire::test(Search::class)->set('city_id', 999999)->assertSee(__('site.search.no_results'));
        $this->assertNotNull($dubai);

        // Text search, price per day (real quote, one day from tomorrow) and sorting.
        $cheap = $this->vehicleIn('Mumbai', ['name' => 'Creta']);
        PricingRule::create(['scope_type' => 'model', 'scope_id' => $cheap->model_id, 'unit' => 'day', 'base_rate' => 500]);
        Livewire::test(Search::class)->set('country_id', $in)
            ->set('q', 'swi')->assertSee('Swift')->assertDontSee('Creta')
            ->set('q', '')->set('max_price', 5)->assertSee('Creta')->assertDontSee('Swift')
            ->set('max_price', null)->set('sort', 'price_asc')->assertSeeInOrder(['Creta', 'Swift'])
            ->set('sort', 'price_desc')->assertSeeInOrder(['Swift', 'Creta']);
    }

    public function test_vehicle_details_availability_and_quote_with_addons_and_coupon(): void
    {
        $helmet = Addon::create(['name' => 'Helmet', 'price' => 200, 'type' => Addon::PER_DAY]);
        Coupon::create(['code' => 'SAVE5', 'type' => Coupon::FIXED, 'value' => 500]);
        Booking::factory()->create(['vehicle_id' => $this->vehicle->id, 'status' => 'confirmed', 'start_at' => '2026-10-20 10:00', 'end_at' => '2026-10-21 10:00']);

        $this->get("/vehicles/{$this->vehicle->id}")->assertOk()->assertSee('Swift')->assertSee('Helmet');
        $this->get('/vehicles/999999')->assertNotFound();

        Livewire::test(VehicleShow::class, ['id' => $this->vehicle->id])
            ->set('from', '2026-10-05T10:00')->set('to', '2026-10-08T10:00')
            ->assertSee(__('site.vehicle.available'))->assertSee('₹30.00')->assertSee('₹5,030.00') // total + deposit
            ->set("qty.{$helmet->id}", 2)->assertSee('₹42.00')                                   // + 2 helmets × 3 days
            ->set('coupon', 'SAVE5')->assertSee('₹37.00')
            ->set('coupon', 'NOPE')->assertSee(__('messages.coupon_invalid'))
            ->set('coupon', '')->set('from', '2026-10-20T12:00')->set('to', '2026-10-20T14:00')
            ->assertSee(__('site.vehicle.unavailable'))
            ->set('from', '2026-09-20T10:00')->assertSee('after now'); // past start: BookingRequest rule message
    }

    // --- Auth -------------------------------------------------------------------------------------------------

    public function test_guest_booking_goes_through_sign_in_and_back(): void
    {
        Livewire::test(VehicleShow::class, ['id' => $this->vehicle->id])
            ->set('from', '2026-10-05T10:00')->set('to', '2026-10-08T10:00')
            ->call('book')->assertRedirect(route('login'));
        $this->assertStringContainsString("/vehicles/{$this->vehicle->id}?", session('url.intended'));

        $intended = session('url.intended');
        $this->customer->forceFill(['password' => 'secret-pass'])->save();
        Livewire::test(Login::class)->set('email', $this->customer->email)->set('password', 'secret-pass')
            ->call('login')->assertRedirect($intended);
        $this->assertAuthenticatedAs($this->customer);
    }

    public function test_register_and_password_login_rules(): void
    {
        Livewire::test(Register::class)->set('name', 'Asha')->set('email', 'asha@example.com')->set('password', 'short')
            ->set('password_confirmation', 'short')->call('register')->assertHasErrors(['password']);

        Livewire::test(Register::class)->set('name', 'Asha')->set('email', 'asha@example.com')->set('phone', '+919911112222')
            ->set('password', 'secret-pass')->set('password_confirmation', 'secret-pass')->call('register')->assertRedirect(route('home'));
        $user = User::where('email', 'asha@example.com')->sole();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        Livewire::test(Login::class)->set('email', 'asha@example.com')->set('password', 'wrong-pass')->call('login')
            ->assertHasErrors(['email' => __('messages.invalid_credentials')]);
        $user->forceFill(['status' => 'suspended'])->save();
        Livewire::test(Login::class)->set('email', 'asha@example.com')->set('password', 'secret-pass')->call('login')
            ->assertHasErrors(['email' => __('messages.account_inactive')]);
        $this->assertGuest();
    }

    public function test_website_registration_is_rate_limited_like_the_api(): void
    {
        for ($i = 0; $i < 10; $i++) { // invalid attempts still count
            Livewire::test(Register::class)->call('register')->assertHasErrors(['name']);
        }
        Livewire::test(Register::class)->set('name', 'Asha')->set('email', 'asha@example.com')
            ->set('password', 'secret-pass')->set('password_confirmation', 'secret-pass')->call('register')
            ->assertHasErrors(['email' => __('site.auth.too_many')]);
        $this->assertDatabaseMissing('users', ['email' => 'asha@example.com']);
    }

    public function test_phone_otp_sign_in(): void
    {
        $this->app->instance(SmsDriver::class, $sms = new class implements SmsDriver
        {
            public array $sent = [];

            public function send(string $to, string $message): void
            {
                $this->sent[] = $message;
            }
        });
        $this->customer->update(['phone' => '+919900000077']);

        $page = Livewire::test(Login::class)->set('mode', 'otp')->set('phone', '+919900000077')->call('sendCode')->assertSet('codeSent', true);
        preg_match('/\d{6}/', $sms->sent[0], $m);
        $page->set('code', $m[0] === '000000' ? '111111' : '000000')->call('verifyCode')->assertHasErrors(['code']);
        $page->set('code', $m[0])->call('verifyCode')->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->customer);

        Livewire::test(Login::class)->set('mode', 'otp')->set('phone', '+10000000000')->call('sendCode')->assertSet('codeSent', true); // no enumeration
        $this->assertCount(1, $sms->sent);
    }

    public function test_suspended_user_is_signed_out_mid_session(): void
    {
        $this->actingAs($this->customer)->get('/account/bookings')->assertOk();
        $this->customer->forceFill(['status' => 'suspended'])->save();

        $this->get('/account/bookings')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/account/wallet')->assertRedirect(route('login'));
    }

    // --- Booking → payment → confirmation ----------------------------------------------------------------------

    public function test_book_pay_with_wallet_and_see_confirmation(): void
    {
        $this->actingAs($this->customer);
        $page = Livewire::test(VehicleShow::class, ['id' => $this->vehicle->id])
            ->set('from', '2026-10-05T10:00')->set('to', '2026-10-08T10:00')->call('book');
        $booking = Booking::sole();
        $page->assertRedirect(route('checkout', $booking));
        $this->assertSame(BookingStatus::Draft, $booking->status);

        $this->get(route('checkout', $booking))->assertOk()->assertSee('₹5,030.00')->assertDontSee(__('site.checkout.pay_stripe'));
        $this->get(route('confirmation', $booking))->assertOk()->assertSee(__('site.confirmation.waiting'));

        Livewire::test(Checkout::class, ['booking' => $booking])->call('payWithWallet')->assertSee(__('messages.wallet_insufficient'));
        $this->assertSame(BookingStatus::Draft, $booking->fresh()->status);

        $this->fund(600000);
        Livewire::test(Checkout::class, ['booking' => $booking])->call('payWithWallet')->assertRedirect(route('confirmation', $booking));

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(97000, app(WalletService::class)->balance($this->customer->id, 'INR'));
        $this->get(route('confirmation', $booking))->assertSee(__('site.confirmation.confirmed'));
        $this->get(route('checkout', $booking))->assertRedirect(route('confirmation', $booking)); // nothing left to pay
    }

    public function test_card_checkout_is_configuration_aware_and_never_fakes_success(): void
    {
        $booking = $this->draft();
        $this->actingAs($this->customer);
        Country::where('iso_code', 'IN')->sole()->pack->update(['config_json' => ['gateways' => ['stripe']]]); // the INR booking may use Stripe

        Livewire::test(Checkout::class, ['booking' => $booking])->assertDontSee(__('site.checkout.pay_stripe'))->assertDontSee(__('site.checkout.pay_razorpay'))
            ->call('payWithCard', 'stripe')->assertSee(__('messages.payment_gateway_unavailable'));
        $this->assertSame(0, Payment::count());

        config(['services.stripe' => ['key' => 'pk_test_1', 'secret' => 'sk_test_1', 'webhook_secret' => 'whsec_1']]);
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_web', 'client_secret' => 'pi_web_secret'])]);

        Livewire::test(Checkout::class, ['booking' => $booking])->assertSee(__('site.checkout.pay_stripe'))->assertDontSee(__('site.checkout.pay_razorpay'))
            ->call('payWithCard', 'stripe')
            ->assertDispatched('start-card-payment', fn ($name, $params) => $params['gateway'] === 'stripe'
                && $params['client']['client_secret'] === 'pi_web_secret' && $params['returnUrl'] === route('confirmation', $booking));

        // The browser hand-off doesn't confirm anything: only the webhook does.
        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        $this->assertSame(Payment::PENDING, Payment::sole()->status);
        Livewire::test(Confirmation::class, ['booking' => $booking])->assertSee(__('site.confirmation.waiting'));
    }

    public function test_cancel_and_extend_from_booking_detail(): void
    {
        $booking = $this->draft();
        $this->fund(700000);
        $this->actingAs($this->customer);
        Livewire::test(Checkout::class, ['booking' => $booking])->call('payWithWallet');

        Livewire::test(BookingShow::class, ['booking' => $booking])
            ->set('newEnd', '2026-10-07T10:00')->call('extend')->assertNotSet('actionError', null) // earlier than the current end
            ->set('newEnd', '2026-10-09T10:00')->call('extend')->assertSee(__('site.booking.extended_notice'))
            ->assertSee(route('checkout', $booking)); // 1000 more due after the extension
        $this->assertSame(4000, $booking->fresh()->grand_total);

        Livewire::test(BookingShow::class, ['booking' => $booking])->call('cancel')->assertSee(__('site.booking.cancelled_notice'));
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(700000, app(WalletService::class)->balance($this->customer->id, 'INR')); // all paid back
        Livewire::test(BookingShow::class, ['booking' => $booking])->call('cancel')->assertSee(__('messages.booking_invalid_status'));
    }

    // --- Isolation -------------------------------------------------------------------------------------------

    public function test_customers_cannot_reach_other_customers_data(): void
    {
        $theirs = $this->draft(User::factory()->create());
        app(WalletService::class)->credit($theirs->user_id, 123456, 'INR', WalletTransaction::TOPUP, 'payment', 1);
        $this->actingAs($this->customer);

        foreach ([route('checkout', $theirs), route('confirmation', $theirs), route('account.bookings.show', $theirs), route('account.bookings.invoice', $theirs)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/account/bookings')->assertOk()->assertSee(__('site.booking.none'));
        $this->get('/account/wallet')->assertOk()->assertDontSee('1,234.56')->assertSee(__('site.wallet.empty'));

        foreach (['/account/bookings', '/account/wallet', '/account/profile', route('checkout', $theirs)] as $url) {
            auth()->logout();
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    // --- Wallet, profile, invoice ------------------------------------------------------------------------------

    public function test_wallet_page_and_topup_waits_for_webhook(): void
    {
        $this->fund(150000);
        $this->actingAs($this->customer);
        $this->get('/account/wallet')->assertOk()->assertSee('₹1,500.00')->assertSee(__('site.wallet.type_topup'))->assertSee(__('site.checkout.no_card'));

        config(['services.razorpay' => ['key_id' => 'rzp_test_1', 'key_secret' => 'rzp_s', 'webhook_secret' => 'rzp_w']]);
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_web'])]);

        Livewire::test(Wallet::class)->assertSet('currency', 'INR')
            ->set('amount', '0')->call('topup', 'razorpay')->assertHasErrors(['amount'])
            ->set('amount', '250.50')->call('topup', 'razorpay')
            ->assertDispatched('start-card-payment', fn ($n, $p) => $p['client']['order_id'] === 'order_web')
            ->assertSee(__('site.wallet.processing'));

        $this->assertSame(25050, Payment::sole()->amount);
        $this->assertSame(150000, app(WalletService::class)->balance($this->customer->id, 'INR')); // unchanged until the webhook
    }

    public function test_profile_update_uses_the_same_rules_as_the_api(): void
    {
        User::factory()->create(['phone' => '+919900000055']);
        $this->actingAs($this->customer);

        Livewire::test(Profile::class)->set('phone', '+919900000055')->call('save')->assertHasErrors(['phone']);
        Livewire::test(Profile::class)->set('locale', 'fr')->call('save')->assertHasErrors(['locale']);
        Livewire::test(Profile::class)->set('name', 'New Name')->set('phone', '+919900000066')->set('locale', 'de')->call('save')
            ->assertRedirect(route('account.profile'));

        $this->customer->refresh();
        $this->assertSame(['New Name', '+919900000066', 'de'], [$this->customer->name, $this->customer->phone, $this->customer->locale]);
        $this->get('/account/profile')->assertSee('Profil gespeichert.'); // site switches to the saved language
    }

    public function test_invoice_download_is_owner_only(): void
    {
        $booking = $this->draft();

        $this->actingAs($this->customer)->get(route('account.bookings.invoice', $booking))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', "attachment; filename=\"invoice-booking-{$booking->id}.pdf\"");
        $this->actingAs(User::factory()->create())->get(route('account.bookings.invoice', $booking))->assertForbidden();
    }

    // --- Languages ---------------------------------------------------------------------------------------------

    public function test_all_five_languages_and_arabic_rtl(): void
    {
        $expected = ['en' => 'Find a bike or car', 'ar' => 'ابحث عن دراجة أو سيارة', 'hi' => 'बाइक या कार खोजें', 'es' => 'Encuentra una moto o un coche', 'de' => 'Motorrad oder Auto finden'];

        foreach ($expected as $locale => $title) {
            $this->post('/language', ['locale' => $locale])->assertRedirect();
            $html = $this->get('/')->assertOk()->assertSee($title)->getContent();
            $this->assertStringContainsString('lang="'.$locale.'"', $html);
            $this->assertStringContainsString($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', $html);
        }

        $this->post('/language', ['locale' => 'fr'])->assertSessionHasErrors('locale');
        session()->forget('locale');
        $this->get('/', ['Accept-Language' => 'es'])->assertSee($expected['es']); // browser language as fallback
    }

    public function test_every_locale_has_the_same_site_keys_as_english(): void
    {
        $keys = fn (string $l) => array_keys(Arr::dot(require lang_path("$l/site.php")));

        foreach (config('app.supported_locales') as $locale) {
            $this->assertSame($keys('en'), $keys($locale), $locale);
        }
    }
}
