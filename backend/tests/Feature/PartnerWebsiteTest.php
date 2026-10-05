<?php

namespace Tests\Feature;

use App\Livewire\Partner\Dashboard;
use App\Livewire\Partner\Vehicles;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerVehicle;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\VehicleModel;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

// Phase 9 website partner area (owner decision: website): apply, vehicles, blockouts, payouts; gated like the API.
class PartnerWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 12:00:00');
        Setting::updateOrCreate(['key' => 'features.partner'], ['value_json' => true]);
        $this->user = User::factory()->create(['country_id' => Country::where('iso_code', 'IN')->value('id')]);
        $this->actingAs($this->user);
    }

    public function test_partner_area_follows_the_feature_toggle(): void
    {
        $this->get('/')->assertSee(route('partner.dashboard'));
        $this->get('/partner')->assertOk()->assertSee(__('site.partner.apply_title'));

        Setting::updateOrCreate(['key' => 'features.partner'], ['value_json' => false]);
        $this->get('/')->assertDontSee(route('partner.dashboard'));
        $this->get('/partner')->assertForbidden();
        $this->get('/partner/vehicles')->assertForbidden();
    }

    public function test_apply_then_wait_for_review(): void
    {
        Livewire::test(Dashboard::class)->call('apply')->assertHasErrors(['payout.account_holder', 'payout.bank_name', 'payout.account_number'])
            ->set('payout.account_holder', 'Asha Rao')->set('payout.bank_name', 'SBI')->set('payout.account_number', '123')
            ->call('apply')->assertHasNoErrors()->assertSee(__('site.partner.status_pending'));

        $this->assertEquals(['account_holder' => 'Asha Rao', 'bank_name' => 'SBI', 'account_number' => '123'], Partner::sole()->payout_details_json); // MySQL JSON reorders keys
    }

    public function test_active_partner_adds_a_vehicle_blocks_dates_and_requests_a_payout(): void
    {
        $partner = Partner::create(['user_id' => $this->user->id, 'status' => Partner::ACTIVE]);
        $branch = Branch::factory()->create(['location_id' => Location::factory()->create(['city_id' => City::where('name', 'Mumbai')->value('id')])->id]);
        $model = VehicleModel::factory()->create();

        $page = Livewire::test(Vehicles::class)
            ->set('modelId', (string) $model->id)->set('branchId', (string) $branch->id)->set('plateNo', 'MH01P9')
            ->call('addVehicle')->assertHasNoErrors()->assertSee(__('site.partner.vehicle_added'))->assertSee(__('site.partner.vehicle_status_pending'));
        $vehicleId = PartnerVehicle::sole()->vehicle_id;

        $page->set("from.$vehicleId", '2026-10-20T10:00')->set("to.$vehicleId", '2026-10-19T10:00')->call('addBlockout', $vehicleId)->assertHasErrors("to.$vehicleId")
            ->set("to.$vehicleId", '2026-10-22T10:00')->set("reason.$vehicleId", 'Service')->call('addBlockout', $vehicleId)
            ->assertHasNoErrors()->assertSee(__('site.partner.blockout_added'))->assertSee('Service');
        $this->assertSame(1, $partner->partnerVehicles()->sole()->vehicle->blockouts()->count());

        $ledger = app(LedgerService::class);
        $ledger->post($ledger->account(LedgerAccount::PARTNER, $partner->id, 'INR'), 500000, LedgerEntry::CREDIT_SHARE);
        Livewire::test(Dashboard::class)->assertSee('₹5,000.00')
            ->set('amount', '6000')->call('requestPayout')->assertSee(__('messages.payout_exceeds_available'))
            ->set('amount', '1500.50')->call('requestPayout')->assertSee(__('site.partner.payout_requested'))->assertSee(__('site.partner.payout_status_pending'));
        $this->assertSame(150050, PayoutRequest::sole()->amount);
    }

    public function test_partner_pages_in_arabic_are_right_to_left(): void
    {
        Partner::create(['user_id' => $this->user->id, 'status' => Partner::ACTIVE]);
        $this->post('/language', ['locale' => 'ar']);

        foreach (['/partner', '/partner/vehicles'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('dir="rtl"', $html);
            $this->assertStringContainsString(e(__('site.partner.title', [], 'ar')), $html);
        }
    }
}
