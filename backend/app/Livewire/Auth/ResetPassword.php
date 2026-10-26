<?php

namespace App\Livewire\Auth;

use App\Services\AccountService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

// The page the reset email links to. Same rules as POST /auth/password/reset; afterwards the user signs in again.
#[Layout('layouts.site')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function save(AccountService $accounts)
    {
        $data = $this->validate(AccountService::resetRules());
        $accounts->resetPassword($data['email'], $data['token'], $data['password']);

        session()->flash('status', __('messages.password_reset_done'));

        return $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password')->title(__('site.auth.reset_title'));
    }
}
