<?php

namespace Tests\Feature;

use App\Filament\Resources\Partners\Pages\ManagePartners;
use App\Filament\Resources\PartnerVehicles\Pages\ManagePartnerVehicles;
use App\Filament\Resources\PayoutBatches\Pages\ManagePayoutBatches;
use App\Filament\Resources\PayoutRequests\Pages\ManagePayoutRequests;
use App\Models\AuditLog;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Partner;
use App\Models\PartnerVehicle;
use App\Models\PayoutBatch;
use App\Models\PayoutRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\LedgerService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Phase 9 admin panel: partner approval, vehicle commission, payout batches. Every decision is audited.
class AdminPartnersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role(Role::ADMIN)->create();
        $this->actingAs($this->admin); // the panel uses the web guard
    }

    private function partner(string $status = Partner::PENDING): Partner
    {
        return Partner::create(['user_id' => User::factory()->create()->id, 'status' => $status, 'payout_details_json' => ['account_holder' => 'Asha', 'bank_name' => 'SBI', 'account_number' => '42']]);
    }

    public function test_pages_render_for_admins_only(): void
    {
        foreach (['partners', 'partner-vehicles', 'payout-requests', 'payout-batches', 'ledger-entries'] as $page) {
            $this->get("/admin/$page")->assertOk();
        }
        $this->actingAs(User::factory()->create())->get('/admin/payout-batches')->assertForbidden();
    }

    public function test_approve_partner_and_vehicle_with_commission(): void
    {
        $partner = $this->partner();
        Livewire::test(ManagePartners::class)->assertSee('SBI')->callTableAction('approve', $partner);
        $this->assertSame(Partner::ACTIVE, $partner->fresh()->status);

        $vehicle = Vehicle::factory()->create(['owner_type' => 'partner', 'owner_id' => $partner->id]);
        $pv = PartnerVehicle::create(['partner_id' => $partner->id, 'vehicle_id' => $vehicle->id, 'commission_pct' => 20, 'status' => PartnerVehicle::PENDING]);
        Livewire::test(ManagePartnerVehicles::class)
            ->callTableAction('approve', $pv, data: ['commission_pct' => 15])->assertHasNoTableActionErrors()
            ->callTableAction('commission', $pv, data: ['commission_pct' => 101])->assertHasTableActionErrors(['commission_pct']);
        $this->assertSame([PartnerVehicle::ACTIVE, 15.0], [$pv->fresh()->status, $pv->fresh()->commission_pct]);

        $this->assertEquals(['partner.approve', 'partner_vehicle.approve'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame($this->admin->id, AuditLog::first()->actor_id);
    }

    public function test_batch_pay_and_reject_payouts_from_the_panel(): void
    {
        $partner = $this->partner(Partner::ACTIVE);
        $ledger = app(LedgerService::class);
        $ledger->post($ledger->account(LedgerAccount::PARTNER, $partner->id, 'INR'), 10000, LedgerEntry::CREDIT_SHARE);
        $payouts = app(PayoutService::class);
        $paid = $payouts->requestPayout($partner, 'INR', 6000);

        Livewire::test(ManagePayoutBatches::class)->callTableAction('create', data: ['currency_code' => 'INR'])->assertNotified('Payout batch updated');
        $batch = PayoutBatch::sole();
        $this->assertSame([6000, PayoutRequest::BATCHED], [$batch->total_amount, $paid->fresh()->status]);

        $rejected = $payouts->requestPayout($partner, 'INR', 1000);
        Livewire::test(ManagePayoutRequests::class)->callTableAction('reject', $rejected);
        Livewire::test(ManagePayoutBatches::class)->callTableAction('paid', $batch);

        $this->assertSame(PayoutBatch::PAID, $batch->fresh()->status);
        $this->assertSame(PayoutRequest::REJECTED, $rejected->fresh()->status);
        $this->assertSame(4000, LedgerAccount::where('owner_type', LedgerAccount::PARTNER)->sole()->balance);
        $this->assertEquals(['payout_batch.create', 'payout_request.reject', 'payout_batch.paid'], AuditLog::orderBy('id')->pluck('action')->all());
    }
}
