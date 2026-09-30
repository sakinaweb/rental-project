<?php

namespace App\Livewire\Auth;

use App\Http\Requests\RegisterRequest;
use App\Models\Country;
use App\Services\AccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Registration with the exact rules of POST /auth/register (RegisterRequest); creates a customer.
#[Layout('layouts.site')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $country_id = null;

    public ?string $locale = null; // the language the visitor is browsing in

    public function mount(): void
    {
        $this->locale = app()->getLocale();
    }

    public function register(AccountService $accounts)
    {
        $data = $this->validate((new RegisterRequest)->rules());
        $data['phone'] = $data['phone'] ?: null;
        unset($data['password_confirmation']);

        $user = $accounts->register($data);
        Auth::guard('web')->login($user);
        session()->regenerate();

        return $this->redirectIntended(route('home'));
    }

    public function render(): View
    {
        return view('livewire.auth.register', ['countries' => Country::active()->orderBy('name')->get()])
            ->title(__('site.auth.create_account'));
    }
}
