<?php

namespace App\Livewire\Account;

use App\Models\Country;
use App\Services\AccountService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Profile: same editable fields and rules as PATCH /me (D3). Email, role and status are not editable here.
#[Layout('layouts.site')]
class Profile extends Component
{
    public string $name = '';

    public ?string $phone = null;

    public ?int $country_id = null;

    public string $locale = 'en';

    public function mount(): void
    {
        $user = auth()->user();
        [$this->name, $this->phone, $this->country_id, $this->locale] = [$user->name, $user->phone, $user->country_id, $user->locale];
    }

    public function save(): void
    {
        $user = auth()->user();
        $data = $this->validate(AccountService::profileRules($user));
        $data['phone'] = ($data['phone'] ?? null) ?: null;
        $user->update($data);

        session()->put('locale', $user->locale); // the site follows the profile language right away
        session()->flash('profile_saved', true);
        $this->redirectRoute('account.profile');
    }

    public function render(): View
    {
        return view('livewire.account.profile', ['countries' => Country::active()->orderBy('name')->get(), 'email' => auth()->user()->email])
            ->title(__('site.nav.profile'));
    }
}
