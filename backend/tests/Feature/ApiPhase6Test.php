<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// New additive API endpoints for the website and the Flutter app (Phase 6, D2–D4).
class ApiPhase6Test extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_addons_are_listed_publicly_and_paginated(): void
    {
        Addon::create(['name' => 'Helmet', 'price' => 200, 'type' => Addon::PER_DAY]);
        Addon::create(['name' => 'Delivery', 'price' => 500, 'type' => Addon::PER_BOOKING]);
        Addon::create(['name' => 'Old', 'price' => 1, 'type' => Addon::PER_DAY])->delete();

        $this->getJson('/api/v1/addons')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['id' => 2, 'name' => 'Delivery', 'price' => 500, 'type' => 'per_booking'])
            ->assertJsonStructure(['links', 'meta' => ['total']]);
    }

    public function test_patch_me_updates_allowed_fields_only(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        User::factory()->create(['phone' => '+919900000011']);
        $in = Country::where('iso_code', 'IN')->value('id');

        $this->patchJson('/api/v1/me', ['name' => 'X'])->assertUnauthorized();

        $this->actingAs($user, 'sanctum');
        $this->patchJson('/api/v1/me', ['phone' => '+919900000011', 'locale' => 'fr', 'country_id' => 999999, 'name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'locale', 'country_id', 'name']);

        $this->patchJson('/api/v1/me', ['name' => 'Renamed', 'phone' => '+919900000022', 'country_id' => $in, 'locale' => 'hi',
            'email' => 'hacker@example.com', 'role_id' => Role::idFor(Role::ADMIN), 'status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.locale', 'hi')->assertJsonPath('data.country_id', $in)
            ->assertJsonPath('data.email', 'me@example.com')->assertJsonPath('data.role', 'customer')->assertJsonPath('data.status', 'active');

        $this->patchJson('/api/v1/me', ['phone' => null])->assertOk()->assertJsonPath('data.phone', null);
        $this->patchJson('/api/v1/me', ['phone' => '+919900000022'])->assertOk(); // own number is fine
    }

    public function test_invoice_endpoint_owner_or_admin_only(): void
    {
        $booking = Booking::factory()->create();
        $url = "/api/v1/bookings/{$booking->id}/invoice";

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum')->get($url)->assertForbidden();

        $response = $this->actingAs($booking->user, 'sanctum')->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->actingAs(User::factory()->role(Role::ADMIN)->create(), 'sanctum')->get($url)->assertOk();
    }
}
