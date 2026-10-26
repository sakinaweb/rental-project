<?php

namespace App\Livewire\Auth;

use App\Services\AccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Request a reset link by email. Same rules and answer as POST /auth/password/forgot.
#[Layout('layouts.site')]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(AccountService $accounts): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $key = 'password-forgot:'.request()->ip(); // same limit as the API route (throttle:5,1)
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('site.auth.too_many')]);
        }
        RateLimiter::hit($key, 60);

        $accounts->sendResetLink($this->email);
        $this->sent = true; // same answer either way, so addresses can't be enumerated
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password')->title(__('site.auth.forgot_title'));
    }
}
