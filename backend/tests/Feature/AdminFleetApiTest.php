<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFleetApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->role(Role::ADMIN)->create(), 'sanctum');
    }

    /** @return array<string, mixed> */
    private function vehiclePayload(array $overrides = []): array
    {
        return $overrides + [
            'model_id' => VehicleModel::factory()->create()->id,
            'branch_id' => Branch::factory()->create()->id,
            'owner_type' => 'company',
            'plate_no' => 'MH01AB1234',
            'status' => 'available',
            'odometer' => 1200,
        ];
    }

    public function test_admin_routes_require_admin_role(): void
    {
        $this->getJson('/api/v1/admin/vehicles')->assertUnauthorized();
        $this->getJson('/api/v1/admin/pricing-rules')->assertUnauthorized();

        foreach ([Role::CUSTOMER, Role::STAFF, Role::PARTNER] as $role) {
            $this->actingAs(User::factory()->role($role)->create(), 'sanctum');
            $this->getJson('/api/v1/admin/vehicles')->assertForbidden();
            $this->postJson('/api/v1/admin/pricing-rules', [])->assertForbidden();
        }
    }

    public function test_vehicle_crud(): void
    {
        $id = $this->asAdmin()->postJson('/api/v1/admin/vehicles', $this->vehiclePayload())
            ->assertCreated()->assertJsonPath('data.plate_no', 'MH01AB1234')->json('data.id');

        $this->putJson("/api/v1/admin/vehicles/$id", $this->vehiclePayload(['plate_no' => 'MH01AB1234', 'status' => 'maintenance', 'odometer' => 1500]))
            ->assertOk()->assertJsonPath('data.status', 'maintenance')->assertJsonPath('data.odometer', 1500);

        // Admin still sees out-of-service vehicles; customers don't.
        $this->getJson('/api/v1/admin/vehicles')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/v1/admin/vehicles/$id")->assertOk();
        $this->getJson("/api/v1/vehicles/$id")->assertNotFound();

        $this->deleteJson("/api/v1/admin/vehicles/$id")->assertNoContent();
        $this->assertSoftDeleted('vehicles', ['id' => $id]);
    }

    public function test_vehicle_validation(): void
    {
        $this->asAdmin();
        Vehicle::factory()->create(['plate_no' => 'TAKEN1'])->delete(); // soft-deleted still owns the plate

        $this->postJson('/api/v1/admin/vehicles', $this->vehiclePayload([
            'plate_no' => 'TAKEN1', 'status' => 'flying', 'odometer' => -1, 'model_id' => 999999,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['plate_no', 'status', 'odometer', 'model_id']);

        $this->postJson('/api/v1/admin/vehicles', $this->vehiclePayload(['owner_type' => 'partner']))
            ->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $this->postJson('/api/v1/admin/vehicles', $this->vehiclePayload(['owner_id' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    }

    public function test_pricing_rule_create_and_list(): void
    {
        $this->asAdmin();
        $vehicle = Vehicle::factory()->create();

        $this->postJson('/api/v1/admin/pricing-rules', ['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 150000])
            ->assertCreated()->assertJsonPath('data.weekend_multiplier', 1)->assertJsonPath('data.monthly_discount_pct', 0);
        $this->postJson('/api/v1/admin/pricing-rules', [
            'scope_type' => 'vehicle', 'scope_id' => $vehicle->id, 'unit' => 'hour', 'base_rate' => 9000,
            'weekend_multiplier' => 1.25, 'monthly_discount_pct' => 10,
        ])->assertCreated()->assertJsonPath('data.scope_id', $vehicle->id);

        $this->getJson('/api/v1/admin/pricing-rules')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.unit', 'hour'); // newest first
    }

    public function test_pricing_rule_validation(): void
    {
        $this->asAdmin();
        $vehicle = Vehicle::factory()->create();
        PricingRule::create(['scope_type' => 'vehicle', 'scope_id' => $vehicle->id, 'unit' => 'day', 'base_rate' => 1000]);
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);

        $post = fn (array $data) => $this->postJson('/api/v1/admin/pricing-rules', $data + ['unit' => 'day', 'base_rate' => 500]);

        $post(['scope_type' => 'global', 'scope_id' => 5])->assertUnprocessable()->assertJsonValidationErrors('scope_id');
        $post(['scope_type' => 'branch'])->assertUnprocessable()->assertJsonValidationErrors('scope_id');
        $post(['scope_type' => 'model', 'scope_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('scope_id');
        $post(['scope_type' => 'vehicle', 'scope_id' => $vehicle->id])->assertUnprocessable()->assertJsonValidationErrors('unit');
        $post(['scope_type' => 'global'])->assertUnprocessable()->assertJsonValidationErrors('unit');
        $post(['scope_type' => 'planet', 'base_rate' => 0, 'unit' => 'week', 'monthly_discount_pct' => 101])
            ->assertUnprocessable()->assertJsonValidationErrors(['scope_type', 'base_rate', 'unit', 'monthly_discount_pct']);

        $post(['scope_type' => 'vehicle', 'scope_id' => $vehicle->id, 'unit' => 'hour'])->assertCreated();
    }
}
