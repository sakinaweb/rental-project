<?php

namespace App\Services;

use App\Http\Requests\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Registration, sign-in and profile rules shared by the API (tokens) and the customer website (sessions).
class AccountService
{
    /** @param  array<string, mixed>  $data  validated with RegisterRequest rules */
    public function register(array $data): User
    {
        return User::create([
            ...$data,
            'locale' => $data['locale'] ?? app()->getLocale(),
            'role_id' => Role::idFor(Role::CUSTOMER),
        ]);
    }

    /** Password check + active-account check, with the same messages everywhere. */
    public function authenticate(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => __('messages.invalid_credentials')]);
        }
        $this->ensureActive($user);

        return $user;
    }

    public function ensureActive(User $user, string $field = 'email'): void
    {
        if (! $user->isActive()) {
            throw ValidationException::withMessages([$field => __('messages.account_inactive')]);
        }
    }

    /**
     * Customer-editable profile fields (PATCH /me and the website profile page). Email, role and status are not editable.
     *
     * @return array<string, mixed>
     */
    public static function profileRules(User $user): array
    {
        $register = (new RegisterRequest)->rules();

        return [
            'name' => ['sometimes', ...$register['name']],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'country_id' => $register['country_id'],
            'locale' => ['sometimes', ...array_diff($register['locale'], ['nullable'])],
        ];
    }
}
