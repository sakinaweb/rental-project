<?php

namespace App\Livewire\Partner;

use App\Models\LedgerEntry;
use App\Models\Partner;
use App\Models\PayoutRequest;
use App\Services\PartnerService;
use App\Services\PayoutService;
use App\Support\Money;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

// Partner area (Phase 9, owner decision: on the website): apply, status, balances, payout requests and earnings.
// Same services and rules as the partner API; the route is behind the partner feature toggle.
#[Layout('layouts.site')]
class Dashboard extends Component
{
    use WithPagination;

    /** @var array{account_holder: string, bank_name: string, account_number: string, routing_code: string} */
    public array $payout = ['account_holder' => '', 'bank_name' => '', 'account_number' => '', 'routing_code' => ''];

    public string $amount = '';

    public string $currency = '';

    public ?string $notice = null;

    public ?string $actionError = null;

    private function partner(): ?Partner
    {
        return Partner::where('user_id', auth()->id())->first();
    }

    public function apply(PartnerService $partners): void
    {
        $this->validate([
            'payout.account_holder' => ['required', 'string', 'max:100'],
            'payout.bank_name' => ['required', 'string', 'max:100'],
            'payout.account_number' => ['required', 'string', 'max:50'],
            'payout.routing_code' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $partners->apply(auth()->user(), array_filter($this->payout, fn ($v) => $v !== ''));
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function requestPayout(PayoutService $payouts): void
    {
        $this->reset('notice', 'actionError');
        $this->validate(['amount' => ['required', 'numeric', 'gt:0'], 'currency' => ['required', 'string', 'size:3']]);

        try {
            $payouts->requestPayout($this->partner() ?? abort(404), $this->currency, Money::toMinor($this->amount, $this->currency));
            $this->notice = __('site.partner.payout_requested');
            $this->reset('amount');
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function render(PayoutService $payouts): View
    {
        $partner = $this->partner();
        $accounts = $partner ? $partner->accounts()->orderBy('currency_code')->get() : collect();
        if ($this->currency === '' && $accounts->isNotEmpty()) {
            $this->currency = $accounts->first()->currency_code;
        }

        return view('livewire.partner.dashboard', [
            'partner' => $partner,
            'balances' => $accounts->map(fn ($a) => ['currency' => $a->currency_code, 'balance' => $a->balance, 'available' => $payouts->available($a)]),
            'payouts' => PayoutRequest::whereIn('account_id', $accounts->pluck('id'))->with('account')->latest('id')->limit(10)->get(),
            'entries' => LedgerEntry::whereIn('account_id', $accounts->pluck('id'))->with('account')->latest('id')->paginate(15),
        ])->title(__('site.partner.title'));
    }
}
