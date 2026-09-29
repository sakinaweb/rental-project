<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_register_creates_customer_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha',
            'email' => 'asha@example.com',
            'phone' => '+919900000001',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'country_id' => Country::where('iso_code', 'IN')->value('id'),
            'locale' => 'hi',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', Role::CUSTOMER)
            ->assertJsonPath('user.locale', 'hi')
            ->assertJsonPath('user.status', 'active');
        $this->assertNotEmpty($response->json('token'));

        $this->withToken($response->json('token'))->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.email', 'asha@example.com');
    }

    public function test_register_cannot_choose_role_or_status(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role_id' => Role::idFor(Role::ADMIN),
            'status' => 'active',
        ])->assertCreated()->assertJsonPath('user.role', Role::CUSTOMER);
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $inactive = Country::where('iso_code', 'DE')->first();
        $inactive->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/register', [
            'email' => 'taken@example.com',
            'phone' => '12345',
            'password' => 'short',
            'country_id' => $inactive->id,
            'locale' => 'fr',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'password', 'country_id', 'locale']);
    }

    public function test_login_returns_token_for_valid_credentials_only(): void
    {
        User::factory()->create(['email' => 'u@example.com', 'password' => 'right-pass']);

        $this->postJson('/api/v1/auth/login', ['email' => 'u@example.com', 'password' => 'wrong-pass'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/login', ['email' => 'u@example.com', 'password' => 'right-pass'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'role']]);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'off@example.com', 'password' => 'right-pass']);
        $user->forceFill(['status' => 'suspended'])->save();

        $this->postJson('/api/v1/auth/login', ['email' => 'off@example.com', 'password' => 'right-pass'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_me_requires_a_token_even_without_json_accept_header(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->get('/api/v1/me')->assertUnauthorized();
    }

    public function test_role_middleware_allows_listed_roles_only(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'role:admin,staff'])->get('/api/v1/_role-probe', fn () => 'ok');

        $this->actingAs(User::factory()->role(Role::STAFF)->create(), 'sanctum')
            ->getJson('/api/v1/_role-probe')->assertOk();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/_role-probe')->assertForbidden();
    }
}
