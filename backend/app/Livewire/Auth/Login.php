<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AccountService;
use App\Services\OtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Sign in with password or phone OTP (D8). Same checks and messages as POST /auth/login and /auth/otp/*.
#[Layout('layouts.site')]
class Login extends Component
{
    public string $mode = 'password'; // password | otp

    public string $email = '';

    public string $password = '';

    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    public function login(AccountService $accounts)
    {
        $this->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $this->throttle('login:'.request()->ip(), 10);

        return $this->signIn($accounts->authenticate($this->email, $this->password));
    }

    public function sendCode(OtpService $otp): void
    {
        $this->validate(['phone' => ['required', 'string', 'max:20']]);
        $this->throttle('otp-request:'.request()->ip(), 5);

        $user = User::where('phone', $this->phone)->first();
        if ($user?->isActive()) {
            $otp->send($user);
        }
        $this->codeSent = true; // same answer either way, so numbers can't be enumerated
        session()->flash('status', __('messages.otp_sent'));
    }

    public function verifyCode(OtpService $otp, AccountService $accounts)
    {
        $this->validate(['phone' => ['required', 'string', 'max:20'], 'code' => ['required', 'digits:6']]);
        $this->throttle('otp-verify:'.request()->ip(), 10);

        $user = User::where('phone', $this->phone)->first();
        if (! $user || ! $otp->verify($this->phone, $this->code)) {
            throw ValidationException::withMessages(['code' => __('messages.otp_invalid')]);
        }
        $accounts->ensureActive($user, 'code');

        return $this->signIn($user);
    }

    private function signIn(User $user)
    {
        Auth::guard('web')->login($user);
        session()->regenerate();

        return $this->redirectIntended(route('home'));
    }

    /** Same limits per minute as the API routes (throttle middleware). */
    private function throttle(string $key, int $perMinute): void
    {
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw ValidationException::withMessages(['email' => __('site.auth.too_many')]);
        }
        RateLimiter::hit($key, 60);
    }

    public function render(): View
    {
        return view('livewire.auth.login')->title(__('site.auth.sign_in'));
    }
}
