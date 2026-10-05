<?php

namespace Tests\Feature;

use App\Enums\BookingStatus as S;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Damage;
use App\Models\Deposit;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerVehicle;
use App\Models\PayoutBatch;
use App\Models\PayoutRequest;
use App\Models\PricingRule;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\LedgerService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// Phase 9 (MD §7.9): partner onboarding, the revenue waterfall in the earnings ledger, payouts, blockouts, feature gating.
class PartnerModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 12:00:00');
        Setting::updateOrCreate(['key' => 'features.partner'], ['value_json' => true]);
    }

    private function countryId(string $iso = 'IN'): int
    {
        return Country::where('iso_code', $iso)->value('id');
    }

    private function partner(string $status = Partner::ACTIVE): Partner
    {
        $user = User::factory()->create(['country_id' => $this->countryId()]);

        return Partner::create(['user_id' => $user->id, 'status' => $status, 'payout_details_json' => ['account_holder' => 'A', 'bank_name' => 'B', 'account_number' => '1']]);
    }

    private function partnerVehicle(Partner $partner, float $commission = 20, string $status = PartnerVehicle::ACTIVE): Vehicle
    {
        $vehicle = Vehicle::factory()->create(['owner_type' => 'partner', 'owner_id' => $partner->id, 'branch_id' => $this->branch()->id]);
        PartnerVehicle::create(['partner_id' => $partner->id, 'vehicle_id' => $vehicle->id, 'commission_pct' => $commission, 'status' => $status]);

        return $vehicle;
    }

    private function branch(string $city = 'Mumbai'): Branch
    {
        return Branch::factory()->create(['location_id' => Location::factory()->create(['city_id' => City::where('name', $city)->value('id')])->id]);
    }

    /** Settles a returned booking after the hold window, with a held deposit and optional approved damage. */
    private function settle(Vehicle $vehicle, int $grand, int $tax, int $deposit = 5000, int $damage = 0): Booking
    {
        $booking = Booking::factory()->create([
            'vehicle_id' => $vehicle->id, 'status' => S::Returned, 'currency_code' => 'INR',
            'grand_total' => $grand, 'tax_total' => $tax, 'deposit_amount' => $deposit,
        ]);
        $booking->deposit()->create(['amount' => $deposit, 'status' => Deposit::HELD]);
        $booking->transitionTo(S::Completed);
        if ($damage) {
            $booking->damages()->create(['description' => 'Scratch', 'cost' => $damage, 'status' => Damage::APPROVED]);
        }
        $this->travel(73)->hours();

        return app(BookingService::class)->settle($booking);
    }

    private function balance(string $type, int $ownerId): int
    {
        return (int) LedgerAccount::where(['owner_type' => $type, 'owner_id' => $ownerId, 'currency_code' => 'INR'])->value('balance');
    }

    public function test_settlement_splits_the_rental_net_of_tax_and_gives_damages_to_the_owner(): void
    {
        $partner = $this->partner();
        $booking = $this->settle($this->partnerVehicle($partner, 20), grand: 11800, tax: 1800, damage: 1200);

        // Base 10000 (tax and deposit excluded): platform 20% = 2000, partner 8000 + the 1200 kept for damages.
        $this->assertSame(2000, $this->balance(LedgerAccount::PLATFORM, 0));
        $this->assertSame(9200, $this->balance(LedgerAccount::PARTNER, $partner->id));
        $this->assertSame(10000 + 1200, (int) LedgerEntry::where('booking_id', $booking->id)->sum('amount')); // nothing created or lost
        $this->assertSame(3800, $booking->deposit->refunded_amount);

        foreach (LedgerAccount::all() as $account) { // running balances agree with the entries
            $this->assertSame($account->balance, (int) $account->entries()->sum('amount'));
            $this->assertSame($account->balance, $account->entries()->latest('id')->value('running_balance'));
        }
    }

    public function test_rounding_keeps_both_shares_adding_up_to_the_base(): void
    {
        $partner = $this->partner();
        $this->settle($this->partnerVehicle($partner, 17.5), grand: 3333, tax: 0);

        $this->assertSame(583, $this->balance(LedgerAccount::PLATFORM, 0)); // 583.275 rounded
        $this->assertSame(2750, $this->balance(LedgerAccount::PARTNER, $partner->id));
    }

    public function test_company_vehicle_revenue_stays_with_the_platform_and_posts_once(): void
    {
        $booking = $this->settle(Vehicle::factory()->create(), grand: 5900, tax: 900, damage: 700);

        $this->assertSame(5000 + 700, $this->balance(LedgerAccount::PLATFORM, 0));
        app(LedgerService::class)->postBookingCompletion($booking, 700); // a second call changes nothing
        $this->assertSame(2, LedgerEntry::count());
    }

    public function test_payout_requests_batches_payment_cancel_and_reject(): void
    {
        $partner = $this->partner();
        $this->settle($this->partnerVehicle($partner, 20), grand: 10000, tax: 0); // partner earns 8000
        $this->actingAs($partner->user, 'sanctum');
        $request = fn (int $amount) => $this->postJson('/api/v1/partner/payout-requests', ['currency_code' => 'inr', 'amount' => $amount]);

        $request(9000)->assertUnprocessable()->assertJsonPath('message', __('messages.payout_exceeds_available'));
        $request(5000)->assertCreated()->assertJsonPath('data.status', 'pending');
        $request(4000)->assertUnprocessable(); // only 3000 left to request
        $this->getJson('/api/v1/partner/dashboard')->assertJsonPath('data.balances.0', ['currency_code' => 'INR', 'balance' => 8000, 'available' => 3000]);

        $admin = User::factory()->create(['role_id' => Role::where('name', Role::ADMIN)->value('id')]);
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/payout-batches', ['currency_code' => 'INR'])
            ->assertCreated()->assertJsonPath('data.total_amount', 5000)->assertJsonPath('data.requests', 1);
        $this->postJson('/api/v1/admin/payout-batches', ['currency_code' => 'INR'])->assertUnprocessable(); // nothing pending now

        $payouts = app(PayoutService::class);
        $payouts->markPaid(PayoutBatch::sole(), $admin);
        $this->assertSame(3000, $this->balance(LedgerAccount::PARTNER, $partner->id));
        $this->assertSame(PayoutRequest::PAID, PayoutRequest::sole()->status);
        $this->assertSame(-5000, LedgerEntry::where('entry_type', LedgerEntry::PAYOUT)->sole()->amount);
        $this->expectExceptionMessage(__('messages.payout_batch_not_pending'));
        $payouts->cancel(PayoutBatch::sole(), $admin);
    }

    public function test_a_cancelled_batch_returns_requests_and_a_rejected_request_frees_the_money(): void
    {
        $partner = $this->partner();
        $this->settle($this->partnerVehicle($partner, 0), grand: 4000, tax: 0);
        $admin = User::factory()->create();
        $payouts = app(PayoutService::class);

        $first = $payouts->requestPayout($partner, 'INR', 3000);
        $payouts->cancel($payouts->createBatch('INR', $admin), $admin);
        $this->assertSame([PayoutRequest::PENDING, null], [$first->fresh()->status, $first->fresh()->payout_batch_id]);

        $payouts->reject($first);
        $account = LedgerAccount::where('owner_type', LedgerAccount::PARTNER)->sole();
        $this->assertSame(4000, $payouts->available($account));
        $this->assertSame(4000, $account->balance); // nothing left the ledger
    }

    public function test_partner_api_is_gated_by_the_global_flag_and_the_country_pack(): void
    {
        $user = User::factory()->create(['country_id' => $this->countryId()]);
        $this->actingAs($user, 'sanctum');
        $apply = fn () => $this->postJson('/api/v1/partner/apply', ['payout_details' => ['account_holder' => 'Asha', 'bank_name' => 'SBI', 'account_number' => '123', 'routing_code' => 'SBIN0001']]);

        Setting::updateOrCreate(['key' => 'features.partner'], ['value_json' => false]);
        $apply()->assertForbidden()->assertJsonPath('message', __('messages.partner_disabled'));
        Setting::updateOrCreate(['key' => 'features.partner'], ['value_json' => true]);
        Country::find($this->countryId())->pack->update(['config_json' => ['features' => ['partner' => false]]]);
        $this->getJson('/api/v1/partner/dashboard')->assertForbidden();
        Country::find($this->countryId())->pack->update(['config_json' => null]);

        $this->getJson('/api/v1/partner/dashboard')->assertNotFound(); // not a partner yet
        $apply()->assertCreated()->assertJsonPath('data.status', 'pending');
        $apply()->assertUnprocessable()->assertJsonPath('message', __('messages.partner_already_applied'));
        $this->assertSame('SBIN0001', Partner::sole()->payout_details_json['routing_code']);
        $this->getJson('/api/v1/partner/dashboard')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v1/partner/vehicles', ['model_id' => VehicleModel::factory()->create()->id, 'branch_id' => $this->branch()->id, 'plate_no' => 'MH01P1'])
            ->assertUnprocessable()->assertJsonPath('message', __('messages.partner_not_active'));
    }

    public function test_partner_vehicle_is_listed_only_once_approved_and_blockouts_keep_it_off_the_market(): void
    {
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);
        $partner = $this->partner();
        $this->actingAs($partner->user, 'sanctum');
        $model = VehicleModel::factory()->create();

        $this->postJson('/api/v1/partner/vehicles', ['model_id' => $model->id, 'branch_id' => $this->branch('Dubai')->id, 'plate_no' => 'X1'])
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id'); // not in the partner's country
        $id = $this->postJson('/api/v1/partner/vehicles', ['model_id' => $model->id, 'branch_id' => $this->branch()->id, 'plate_no' => 'MH01P1'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.commission_pct', 20)->json('data.id');

        $listed = fn () => collect($this->getJson('/api/v1/vehicles')->json('data'))->pluck('id')->contains($id);
        $this->assertFalse($listed());
        PartnerVehicle::where('vehicle_id', $id)->update(['status' => PartnerVehicle::ACTIVE]);
        $this->assertTrue($listed());
        $partner->update(['status' => Partner::SUSPENDED]);
        $this->assertFalse($listed());
        $partner->update(['status' => Partner::ACTIVE]);

        $from = Carbon::parse('2026-10-20 10:00');
        $to = Carbon::parse('2026-10-22 10:00');
        $blockout = $this->postJson("/api/v1/partner/vehicles/$id/blockout", ['start_at' => $from->toIso8601String(), 'end_at' => $to->toIso8601String(), 'reason' => 'Service'])
            ->assertCreated()->json('data.id');
        $this->assertFalse(app(AvailabilityService::class)->isAvailable($id, $from->copy()->addDay(), $to->copy()->addDays(2)));
        $period = '?from='.urlencode($from->toIso8601String()).'&to='.urlencode($to->toIso8601String());
        $this->assertNotContains($id, collect($this->getJson('/api/v1/vehicles'.$period)->json('data'))->pluck('id'));

        $this->deleteJson("/api/v1/partner/vehicles/$id/blockouts/$blockout")->assertNoContent();
        $this->assertTrue(app(AvailabilityService::class)->isAvailable($id, $from, $to));

        // A blockout can't take away a customer's booking; another partner's vehicle is not found.
        app(BookingService::class)->create(User::factory()->create(), Vehicle::find($id), $from, $to);
        $this->postJson("/api/v1/partner/vehicles/$id/blockout", ['start_at' => $from->toIso8601String(), 'end_at' => $to->toIso8601String()])
            ->assertUnprocessable()->assertJsonPath('message', __('messages.partner_blockout_booked'));
        $other = $this->partnerVehicle($this->partner());
        $this->postJson("/api/v1/partner/vehicles/{$other->id}/blockout", ['start_at' => $from->toIso8601String(), 'end_at' => $to->toIso8601String()])->assertNotFound();
    }

    public function test_earnings_show_only_the_partners_own_entries(): void
    {
        $mine = $this->partner();
        $theirs = $this->partner();
        $this->settle($this->partnerVehicle($mine, 10), grand: 1000, tax: 0);
        $this->settle($this->partnerVehicle($theirs, 10), grand: 5000, tax: 0);

        $this->actingAs($mine->user, 'sanctum')->getJson('/api/v1/partner/earnings?currency_code=INR')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 900)->assertJsonPath('data.0.entry_type', 'credit_share');
    }
}
