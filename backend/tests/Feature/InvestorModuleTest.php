<?php

namespace Tests\Feature;

use App\Enums\BookingStatus as S;
use App\Filament\Resources\Investments\Pages\ManageInvestments;
use App\Livewire\Investor\Dashboard;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Deposit;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\Investor;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerVehicle;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleModel;
use App\Services\BookingService;
use App\Services\InvestorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

// Phase 11 (MD §7.11): investor onboarding (KYC + risk disclosure), admin-confirmed investments on company vehicles,
// the investors' share in the settlement waterfall, payouts, statements and feature gating.
class InvestorModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 12:00:00');
        Setting::updateOrCreate(['key' => 'features.investor'], ['value_json' => true]);
        Country::find($this->countryId())->pack->update(['config_json' => ['features' => ['investor' => true]]]);
    }

    private function countryId(string $iso = 'IN'): int
    {
        return Country::where('iso_code', $iso)->value('id');
    }

    private function investor(string $kyc = Investor::KYC_APPROVED, bool $accepted = true): Investor
    {
        $investor = Investor::create([
            'user_id' => User::factory()->create(['country_id' => $this->countryId()])->id, 'kyc_status' => $kyc,
            'payout_details_json' => ['account_holder' => 'A', 'bank_name' => 'B', 'account_number' => '1'],
        ]);
        if ($accepted) {
            app(InvestorService::class)->acknowledge($investor);
        }

        return $investor;
    }

    private function plan(float $pct = 20, ?int $categoryId = null, string $iso = 'IN'): InvestmentPlan
    {
        return InvestmentPlan::create([
            'name' => "Plan $pct", 'country_id' => $this->countryId($iso), 'vehicle_category_id' => $categoryId,
            'min_amount' => 100000, 'term_months' => 12, 'revenue_share_pct' => $pct, 'status' => InvestmentPlan::ACTIVE,
        ]);
    }

    private function vehicle(string $city = 'Mumbai', array $attrs = []): Vehicle
    {
        $branch = Branch::factory()->create(['location_id' => Location::factory()->create(['city_id' => City::where('name', $city)->value('id')])->id]);

        return Vehicle::factory()->create($attrs + ['branch_id' => $branch->id]);
    }

    /** An active investment of $amount placed on the given vehicles (vehicle_id => amount). */
    private function active(Investor $investor, InvestmentPlan $plan, array $allocations): Investment
    {
        $investment = app(InvestorService::class)->invest($investor, $plan, array_sum($allocations));

        return app(InvestorService::class)->activate($investment, $allocations);
    }

    private function settle(Vehicle $vehicle, int $grand, int $tax, string $start = '2026-10-12 10:00'): Booking
    {
        $booking = Booking::factory()->create([
            'vehicle_id' => $vehicle->id, 'status' => S::Returned, 'currency_code' => 'INR',
            'grand_total' => $grand, 'tax_total' => $tax, 'deposit_amount' => 5000, 'start_at' => $start,
        ]);
        $booking->deposit()->create(['amount' => 5000, 'status' => Deposit::HELD]);
        $booking->transitionTo(S::Completed);
        $this->travel(73)->hours();

        return app(BookingService::class)->settle($booking);
    }

    private function balance(string $type, int $ownerId): int
    {
        return (int) LedgerAccount::where(['owner_type' => $type, 'owner_id' => $ownerId, 'currency_code' => 'INR'])->value('balance');
    }

    public function test_investor_api_is_gated_by_the_global_flag_and_the_country_pack(): void
    {
        $this->actingAs(User::factory()->create(['country_id' => $this->countryId()]), 'sanctum');
        $this->getJson('/api/v1/investment-plans')->assertOk();

        Country::find($this->countryId())->pack->update(['config_json' => ['features' => ['investor' => false]]]);
        $this->getJson('/api/v1/investment-plans')->assertForbidden()->assertJsonPath('message', __('messages.investor_disabled'));

        Country::find($this->countryId())->pack->update(['config_json' => ['features' => ['investor' => true]]]);
        Setting::updateOrCreate(['key' => 'features.investor'], ['value_json' => false]);
        $this->getJson('/api/v1/investor/dashboard')->assertForbidden();
        $this->get('/investor')->assertForbidden();
        $this->get('/')->assertDontSee(route('investor.dashboard'));
    }

    public function test_onboarding_needs_approved_kyc_and_the_current_disclosure_before_investing(): void
    {
        $user = User::factory()->create(['country_id' => $this->countryId()]);
        $plan = $this->plan();
        $abroad = $this->plan(iso: 'AE');
        $this->actingAs($user, 'sanctum');
        $invest = fn (int $amount, ?int $planId = null) => $this->postJson('/api/v1/investments', ['plan_id' => $planId ?? $plan->id, 'amount' => $amount]);

        $this->getJson('/api/v1/investor/dashboard')->assertNotFound()->assertJsonPath('message', __('messages.investor_not_found'));
        $this->postJson('/api/v1/investor/apply', ['payout_details' => ['account_holder' => 'A']])->assertUnprocessable();
        $this->postJson('/api/v1/investor/apply', ['payout_details' => ['account_holder' => 'A', 'bank_name' => 'B', 'account_number' => '1']])
            ->assertCreated()->assertJsonPath('data.kyc_status', 'pending');
        $this->postJson('/api/v1/investor/apply', ['payout_details' => ['account_holder' => 'A', 'bank_name' => 'B', 'account_number' => '1']])
            ->assertUnprocessable()->assertJsonPath('message', __('messages.investor_already_applied'));

        $invest(100000)->assertUnprocessable()->assertJsonPath('message', __('messages.investor_kyc_required'));
        Investor::where('user_id', $user->id)->update(['kyc_status' => Investor::KYC_APPROVED]);
        $invest(100000)->assertUnprocessable()->assertJsonPath('message', __('messages.investor_disclosure_required'));

        $this->getJson('/api/v1/investor/disclosure')->assertJsonPath('data.version', InvestorService::DISCLOSURE_VERSION)->assertJsonPath('data.text', __('site.investor.disclosure'));
        $this->postJson('/api/v1/investor/disclosure', ['version' => 'old'])->assertUnprocessable();
        $this->postJson('/api/v1/investor/disclosure', ['version' => InvestorService::DISCLOSURE_VERSION])->assertCreated();
        $this->assertNotNull(Investor::where('user_id', $user->id)->value('risk_ack_at'));

        $this->getJson('/api/v1/investment-plans')->assertJsonCount(1, 'data')->assertJsonPath('data.0.currency_code', 'INR'); // not the UAE plan
        $invest(99999)->assertUnprocessable()->assertJsonPath('message', __('messages.investment_below_minimum'));
        $invest(100000, $abroad->id)->assertUnprocessable()->assertJsonPath('message', __('messages.investment_plan_closed'));
        $invest(250000)->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount', 250000)->assertJsonPath('data.currency_code', 'INR');

        $plan->update(['status' => InvestmentPlan::CLOSED]);
        $invest(100000)->assertUnprocessable()->assertJsonPath('message', __('messages.investment_plan_closed'));
    }

    public function test_activation_places_the_whole_amount_on_company_vehicles_of_the_plan(): void
    {
        $bikes = VehicleCategory::factory()->create(['type' => 'bike']);
        $plan = $this->plan(categoryId: $bikes->id);
        $bike = $this->vehicle(attrs: ['model_id' => VehicleModel::factory()->create(['category_id' => $bikes->id])->id]);
        $car = $this->vehicle();
        $partner = Partner::create(['user_id' => User::factory()->create()->id, 'status' => Partner::ACTIVE]);
        $partnerBike = $this->vehicle(attrs: ['model_id' => $bike->model_id, 'owner_type' => 'partner', 'owner_id' => $partner->id]);
        PartnerVehicle::create(['partner_id' => $partner->id, 'vehicle_id' => $partnerBike->id, 'commission_pct' => 20, 'status' => PartnerVehicle::ACTIVE]);
        $dubaiBike = $this->vehicle('Dubai', ['model_id' => $bike->model_id]);
        $service = app(InvestorService::class);
        $investment = $service->invest($this->investor(), $plan, 200000);

        foreach ([[$bike->id => 150000], [$car->id => 200000], [$partnerBike->id => 200000], [$dubaiBike->id => 200000]] as $wrong) {
            try {
                $service->activate($investment, $wrong);
                $this->fail('activated with '.json_encode($wrong));
            } catch (\DomainException $e) {
                $this->assertContains($e->getMessage(), [__('messages.investment_allocation_total'), __('messages.investment_allocation_vehicles')]);
            }
        }

        $service->activate($investment, [$bike->id => 200000]);
        $this->assertSame(Investment::ACTIVE, $investment->fresh()->status);
        $this->assertSame('2027-10-10 12:00:00', $investment->fresh()->ends_at->toDateTimeString()); // 12-month term from today
        $this->expectExceptionMessage(__('messages.investment_not_pending'));
        $service->activate($investment, [$bike->id => 200000]);
    }

    public function test_settlement_pays_each_plan_share_split_by_allocation_out_of_the_platform_share(): void
    {
        $vehicle = $this->vehicle();
        $a = $this->investor();
        $b = $this->investor();
        $this->active($a, $this->plan(20), [$vehicle->id => 300000]);
        $this->active($b, $this->plan(10), [$vehicle->id => 100000]);

        $booking = $this->settle($vehicle, grand: 11800, tax: 1800); // base 10000

        // A: 20% pool 2000 × 3/4 = 1500; B: 10% pool 1000 × 1/4 = 250; platform keeps the rest.
        $this->assertSame(1500, $this->balance(LedgerAccount::INVESTOR, $a->id));
        $this->assertSame(250, $this->balance(LedgerAccount::INVESTOR, $b->id));
        $this->assertSame(8250, $this->balance(LedgerAccount::PLATFORM, 0));
        $this->assertSame(10000, (int) LedgerEntry::where('booking_id', $booking->id)->sum('amount')); // nothing created or lost
        foreach (LedgerAccount::all() as $account) {
            $this->assertSame($account->balance, (int) $account->entries()->sum('amount'));
        }
    }

    public function test_only_bookings_starting_within_an_active_term_earn_and_rounding_favours_no_one_unfairly(): void
    {
        $vehicle = $this->vehicle();
        $investor = $this->investor();
        $investment = $this->active($investor, $this->plan(17.5), [$vehicle->id => 100000]);

        $this->settle($vehicle, grand: 3333, tax: 0, start: '2026-10-01 10:00'); // before the term started
        $this->assertSame(0, $this->balance(LedgerAccount::INVESTOR, $investor->id));

        $this->settle($vehicle, grand: 3333, tax: 0);
        $this->assertSame(583, $this->balance(LedgerAccount::INVESTOR, $investor->id)); // 583.275 rounded down
        $this->assertSame(3333 + 2750, $this->balance(LedgerAccount::PLATFORM, 0));

        app(InvestorService::class)->end($investment, Investment::CLOSED);
        $this->settle($vehicle, grand: 1000, tax: 0, start: '2026-10-20 10:00');
        $this->assertSame(583, $this->balance(LedgerAccount::INVESTOR, $investor->id)); // closed: nothing more
    }

    public function test_dashboard_statement_and_payout_requests(): void
    {
        $vehicle = $this->vehicle();
        $investor = $this->investor();
        $this->active($investor, $this->plan(20), [$vehicle->id => 100000]);
        $this->settle($vehicle, grand: 10000, tax: 0); // earns 2000
        $this->actingAs($investor->user, 'sanctum');
        $request = fn (int $amount) => $this->postJson('/api/v1/investor/payout-requests', ['currency_code' => 'inr', 'amount' => $amount]);

        $this->getJson('/api/v1/investor/dashboard')
            ->assertJsonPath('data.kyc_status', 'approved')->assertJsonPath('data.disclosure_accepted', true)
            ->assertJsonPath('data.investments.0.status', 'active')->assertJsonPath('data.investments.0.vehicles.0.plate_no', $vehicle->plate_no)
            ->assertJsonPath('data.balances.0', ['currency_code' => 'INR', 'balance' => 2000, 'available' => 2000]);

        $request(2500)->assertUnprocessable()->assertJsonPath('message', __('messages.payout_exceeds_available'));
        $request(1500)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->getJson('/api/v1/investor/dashboard')->assertJsonPath('data.balances.0.available', 500);

        $this->getJson('/api/v1/investor/statements/2026-10')
            ->assertJsonPath('data.accounts.0.opening_balance', 0)->assertJsonPath('data.accounts.0.earned', 2000)
            ->assertJsonPath('data.accounts.0.closing_balance', 2000)->assertJsonCount(1, 'data.accounts.0.entries');
        $this->getJson('/api/v1/investor/statements/2026-09')->assertJsonPath('data.accounts.0.earned', 0);
        $this->getJson('/api/v1/investor/statements/2026-13')->assertNotFound();

        $investor->update(['kyc_status' => Investor::KYC_REJECTED]);
        $request(100)->assertUnprocessable()->assertJsonPath('message', __('messages.investor_kyc_required'));
    }

    public function test_website_walks_from_application_to_a_pending_investment(): void
    {
        $user = User::factory()->create(['country_id' => $this->countryId()]);
        $plan = $this->plan();
        $this->actingAs($user);
        $this->get('/')->assertSee(route('investor.dashboard'));

        Livewire::test(Dashboard::class)->assertSee(__('site.investor.apply_title'))
            ->set('payout', ['account_holder' => 'A', 'bank_name' => 'B', 'account_number' => '1', 'routing_code' => ''])
            ->call('apply')->assertSee(__('site.investor.kyc_pending'));

        Investor::where('user_id', $user->id)->update(['kyc_status' => Investor::KYC_APPROVED]);
        Livewire::test(Dashboard::class)->assertSee(__('site.investor.disclosure'))
            ->call('acknowledge')->assertHasErrors('accepted')
            ->set('accepted', true)->call('acknowledge')->assertSee($plan->name)
            ->set("amounts.{$plan->id}", '500')->call('invest', $plan->id)->assertSee(__('messages.investment_below_minimum')) // ₹500 < ₹1,000
            ->set("amounts.{$plan->id}", '2500')->call('invest', $plan->id)->assertSee('INV-');

        $investment = Investment::sole();
        $this->assertSame([250000, Investment::PENDING], [$investment->amount, $investment->status]);
    }

    public function test_admin_activates_from_the_investments_page_and_it_is_audited(): void
    {
        $admin = User::factory()->role(Role::ADMIN)->create();
        $vehicle = $this->vehicle();
        $investment = app(InvestorService::class)->invest($this->investor(), $this->plan(), 150000);
        $this->actingAs($admin);

        foreach (['investment-plans', 'investors', 'investments'] as $page) {
            $this->get("/admin/$page")->assertOk();
        }

        Livewire::test(ManageInvestments::class)
            ->callTableAction('activate', $investment, ['allocations' => [['vehicle_id' => $vehicle->id, 'allocated_amount' => 100000]]])
            ->assertNotified(__('messages.investment_allocation_total'));
        $this->assertSame(Investment::PENDING, $investment->fresh()->status);

        Livewire::test(ManageInvestments::class)
            ->callTableAction('activate', $investment, ['allocations' => [['vehicle_id' => $vehicle->id, 'allocated_amount' => 150000]]]);
        $this->assertSame(Investment::ACTIVE, $investment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'investment.activate', 'subject_id' => $investment->id]);
    }
}
