<?php

namespace App\Livewire\Account;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

// My bookings: only the signed-in customer's own bookings (same query as GET /bookings).
#[Layout('layouts.site')]
class Bookings extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('livewire.account.bookings', [
            'bookings' => auth()->user()->bookings()->with(['vehicle.vehicleModel.brand'])->latest('id')->paginate(10),
        ])->title(__('site.nav.my_bookings'));
    }
}
