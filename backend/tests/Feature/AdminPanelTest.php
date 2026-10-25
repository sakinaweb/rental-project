<?php

namespace Tests\Feature;

use App\Enums\BookingStatus as S;
use App\Filament\Pages\RevenueReport;
use App\Filament\Pages\Settings;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\RelationManagers\DamagesRelationManager;
use App\Filament\Resources\Deposits\DepositResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PricingRules\Pages\ManagePricingRules;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\TodaysOperations;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Damage;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->admin = User::factory()->role(Role::ADMIN)->create();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin); // web guard: the panel uses sessions
    }

    public function test_only_active_admins_can_open_the_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->role(Role::STAFF)->create())->get('/admin')->assertForbidden();
        $suspended = User::factory()->role(Role::ADMIN)->create();
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($suspended)->get('/admin')->assertForbidden();

        $this->asAdmin()->get('/admin')->assertOk()->assertSee('Operations');
    }

    public function test_every_admin_page_renders(): void
    {
        $booking = Booking::factory()->create();
        $this->asAdmin();

        foreach (['bookings', 'payments', 'deposits', 'wallet-transactions', 'vehicles', 'vehicle-models', 'vehicle-categories', 'brands',
            'pricing-rules', 'tax-rules', 'addons', 'coupons', 'countries', 'cities', 'locations', 'branches', 'users', 'translations',
            'audit-logs', 'settings', 'revenue-report', "bookings/{$booking->id}"] as $path) {
            $this->get("/admin/{$path}")->assertOk();
        }
    }

    public function test_money_screens_are_read_only_and_bookings_have_no_forms(): void
    {
        $this->asAdmin();
        foreach (['bookings/create', 'payments/create', 'bookings/1/edit'] as $path) {
            $this->get("/admin/{$path}")->assertNotFound();
        }
        foreach ([PaymentResource::class, DepositResource::class,
            WalletTransactionResource::class, AuditLogResource::class,
            BookingResource::class, UserResource::class] as $resource) {
            $this->assertFalse($resource::canCreate(), $resource);
        }
    }

    public function test_booking_actions_follow_the_state_machine_and_are_audited(): void
    {
        $booking = Booking::factory()->create(['status' => S::Confirmed]);
        $this->asAdmin();
        $page = Livewire::test(ViewBooking::class, ['record' => $booking->id]);

        $page->assertActionHidden('return')->assertActionHidden('complete')->assertActionHidden('settle')
            ->assertActionVisible('pick_up')->assertActionVisible('no_show')->assertActionVisible('cancel');

        $page->callAction('pick_up');
        $this->assertSame(S::PickedUp, $booking->fresh()->status);
        $page->assertActionHidden('pick_up')->assertActionHidden('cancel')->assertActionVisible('return');

        $page->callAction('return')->callAction('complete');
        $this->assertSame(S::Completed, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->completed_at);

        $page->callAction('settle')->assertNotified(__('messages.booking_hold_window')); // refused inside the window
        $this->assertSame(S::Completed, $booking->fresh()->status);

        $this->assertSame(['booking.pick_up', 'booking.return', 'booking.complete'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertEquals(['from' => 'confirmed', 'to' => 'picked_up'], AuditLog::first()->meta_json); // MySQL JSON reorders keys
        $this->assertSame($this->admin->id, AuditLog::first()->actor_id);
    }

    public function test_admin_cancel_refunds_through_the_booking_service(): void
    {
        $booking = Booking::factory()->create(['status' => S::Confirmed, 'grand_total' => 3000, 'deposit_amount' => 0]);
        Payment::create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'gateway' => 'stripe', 'gateway_ref' => 'pi_x',
            'amount' => 3000, 'currency_code' => 'INR', 'status' => Payment::SUCCEEDED]);
        $this->asAdmin();

        Livewire::test(ViewBooking::class, ['record' => $booking->id])->callAction('cancel');

        $this->assertSame(S::Cancelled, $booking->fresh()->status);
        $this->assertSame(3000, app(WalletService::class)->balance($booking->user_id, 'INR'));
        $this->assertSame(WalletTransaction::REFUND, WalletTransaction::sole()->type);
        $this->assertSame('booking.cancel', AuditLog::sole()->action);
    }

    public function test_assign_and_invoice_actions(): void
    {
        $booking = Booking::factory()->create(['status' => S::Confirmed, 'start_at' => '2026-11-02 10:00', 'end_at' => '2026-11-03 10:00']);
        $other = Vehicle::factory()->create();
        $this->asAdmin();

        Livewire::test(ViewBooking::class, ['record' => $booking->id])
            ->callAction('assign', data: ['vehicle_id' => $other->id])
            ->callAction('invoice')->assertFileDownloaded("invoice-booking-{$booking->id}.pdf");

        $this->assertSame($other->id, $booking->fresh()->vehicle_id);
        $this->assertSame('booking.assign', AuditLog::sole()->action);
    }

    public function test_damages_are_recorded_decided_and_audited(): void
    {
        $booking = Booking::factory()->create(['status' => S::Returned]);
        $this->asAdmin();
        $rm = Livewire::test(DamagesRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => ViewBooking::class]);

        $rm->callTableAction('create', data: ['description' => 'Scratch on door', 'cost' => 2500])->assertHasNoTableActionErrors();
        $damage = Damage::sole();
        $this->assertSame(Damage::REPORTED, $damage->status);

        $rm->callTableAction('approve', $damage);
        $this->assertSame(Damage::APPROVED, $damage->fresh()->status);
        $rm->assertTableActionHidden('waive', $damage);

        $this->assertSame(['damage.record', 'damage.approve'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_settings_save_feature_flags_and_numbers_with_audit(): void
    {
        $this->asAdmin();

        Livewire::test(Settings::class)
            ->assertSchemaStateSet(['features_investor' => false, 'availability_buffer_minutes' => 60], 'form')
            ->fillForm(['features_investor' => true, 'payment_timeout_minutes' => 45])
            ->call('save')->assertHasNoFormErrors();

        $this->assertTrue(Setting::valueOf('features.investor'));
        $this->assertSame(45, Setting::valueOf('payment.timeout_minutes'));
        $this->assertSame(60, Setting::valueOf('availability.buffer_minutes')); // untouched
        $log = AuditLog::sole();
        $this->assertSame('settings.update', $log->action);
        $this->assertEquals(['features.investor' => ['from' => false, 'to' => true], 'payment.timeout_minutes' => ['from' => 30, 'to' => 45]], $log->meta_json);

        Livewire::test(Settings::class)->fillForm(['availability_buffer_minutes' => -5])->call('save')
            ->assertHasFormErrors(['availability_buffer_minutes']);
        $this->assertSame(60, Setting::valueOf('availability.buffer_minutes'));
    }

    public function test_suspending_a_user_revokes_tokens_and_is_audited(): void
    {
        $customer = User::factory()->create();
        $customer->createToken('app');
        $this->asAdmin();

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $customer, data: ['status' => 'suspended'])->assertHasNoTableActionErrors();

        $this->assertSame('suspended', $customer->fresh()->status);
        $this->assertSame(0, $customer->tokens()->count());
        $this->assertEquals(['from' => ['role' => 'customer', 'status' => 'active'], 'to' => ['role' => 'customer', 'status' => 'suspended']], AuditLog::sole()->meta_json);
    }

    public function test_forms_keep_the_api_validation_rules(): void
    {
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);
        Vehicle::factory()->create(['plate_no' => 'TAKEN1']);
        $this->asAdmin();

        Livewire::test(ManagePricingRules::class)
            ->callAction('create', data: ['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 500, 'weekend_multiplier' => 1, 'monthly_discount_pct' => 0])
            ->assertHasActionErrors(['unit']);
        Livewire::test(ManagePricingRules::class)
            ->callAction('create', data: ['scope_type' => 'vehicle', 'scope_id' => 999999, 'unit' => 'day', 'base_rate' => 500, 'weekend_multiplier' => 1, 'monthly_discount_pct' => 0])
            ->assertHasActionErrors(['scope_id']);
        Livewire::test(ManagePricingRules::class)
            ->callAction('create', data: ['scope_type' => 'global', 'unit' => 'hour', 'base_rate' => 500, 'weekend_multiplier' => 1, 'monthly_discount_pct' => 0])
            ->assertHasNoActionErrors();
        $this->assertNull(PricingRule::where('unit', 'hour')->sole()->scope_id);

        $model = Vehicle::first()->model_id;
        $branch = Vehicle::first()->branch_id;
        Livewire::test(ManageVehicles::class)
            ->callAction('create', data: ['model_id' => $model, 'branch_id' => $branch, 'plate_no' => 'TAKEN1', 'status' => 'available', 'owner_type' => 'company', 'odometer' => 0])
            ->assertHasActionErrors(['plate_no']);
    }

    public function test_revenue_report_page_and_csv_export(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $booking = Booking::factory()->create(['status' => 'completed', 'currency_code' => 'INR', 'subtotal' => 10100, 'discount_total' => 100, 'tax_total' => 1800, 'grand_total' => 11800]);
        $booking->forceFill(['completed_at' => '2026-10-05 10:00'])->save();
        $this->asAdmin();

        Livewire::test(RevenueReport::class)
            ->assertSee('2026-10-05')->assertSee('₹100.00') // net revenue 10000 minor units
            ->callAction('export')->assertFileDownloaded();
    }

    public function test_dashboard_lists_what_is_due_today_and_links_numbers_to_bookings(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $pickup = Booking::factory()->create(['status' => S::Confirmed, 'start_at' => '2026-10-15 16:00', 'end_at' => '2026-10-16 16:00']);
        $overdue = Booking::factory()->create(['status' => S::PickedUp, 'start_at' => '2026-10-12 10:00', 'end_at' => '2026-10-14 10:00']);
        $later = Booking::factory()->create(['status' => S::Confirmed, 'start_at' => '2026-10-20 10:00', 'end_at' => '2026-10-21 10:00']);
        $done = Booking::factory()->create(['status' => S::Returned, 'start_at' => '2026-10-14 10:00', 'end_at' => '2026-10-15 10:00']);
        $this->asAdmin();

        Livewire::test(TodaysOperations::class)
            ->assertCanSeeTableRecords([$overdue, $pickup], inOrder: true)
            ->assertCanNotSeeTableRecords([$later, $done])
            ->assertSee('Late');

        Livewire::test(OperationsOverview::class)
            ->assertSee('Overdue returns')
            ->assertSee(BookingResource::getUrl('index', ['filters' => ['status' => ['value' => 'picked_up']]]), escape: false);
    }
}
