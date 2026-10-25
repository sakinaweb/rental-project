<?php

namespace App\Livewire\Investor;

use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\Investor;
use App\Models\PayoutRequest;
use App\Services\InvestorService;
use App\Services\PayoutService;
use App\Support\Money;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Investor area (Phase 11): apply → KYC review → accept the risk disclosure → invest → earnings and payouts.
// Same services and rules as the investor API; the route is behind the investor feature toggle.
#[Layout('layouts.site')]
class Dashboard extends Component
{
    /** @var array{account_holder: string, bank_name: string, account_number: string, routing_code: string} */
    public array $payout = ['account_holder' => '', 'bank_name' => '', 'account_number' => '', 'routing_code' => ''];

    public bool $accepted = false;

    /** @var array<int, string> plan_id => amount typed in major units */
    public array $amounts = [];

    public string $amount = '';

    public string $currency = '';

    public ?string $notice = null;

    public ?string $actionError = null;

    private function investor(): ?Investor
    {
        return Investor::where('user_id', auth()->id())->first();
    }

    public function apply(InvestorService $investors): void
    {
        $this->reset('notice', 'actionError');
        $this->validate([
            'payout.account_holder' => ['required', 'string', 'max:100'],
            'payout.bank_name' => ['required', 'string', 'max:100'],
            'payout.account_number' => ['required', 'string', 'max:50'],
            'payout.routing_code' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $investors->apply(auth()->user(), array_filter($this->payout, fn ($v) => $v !== ''));
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function acknowledge(InvestorService $investors): void
    {
        $this->validate(['accepted' => ['accepted']]);
        $investors->acknowledge($this->investor() ?? abort(404));
    }

    public function invest(InvestorService $investors, int $planId): void
    {
        $this->reset('notice', 'actionError');
        $plan = InvestmentPlan::with('country')->findOrFail($planId);
        $this->validate(["amounts.$planId" => ['required', 'numeric', 'gt:0']], [], ["amounts.$planId" => __('site.partner.amount')]);

        try {
            $investment = $investors->invest($this->investor() ?? abort(404), $plan, Money::toMinor($this->amounts[$planId], $plan->currency_code));
            $this->notice = __('site.investor.invested', ['amount' => Money::format($investment->amount, $plan->currency_code), 'id' => $investment->id]);
            unset($this->amounts[$planId]);
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function requestPayout(PayoutService $payouts): void
    {
        $this->reset('notice', 'actionError');
        $this->validate(['amount' => ['required', 'numeric', 'gt:0'], 'currency' => ['required', 'string', 'size:3']]);

        try {
            $payouts->requestInvestorPayout($this->investor() ?? abort(404), $this->currency, Money::toMinor($this->amount, $this->currency));
            $this->notice = __('site.partner.payout_requested');
            $this->reset('amount');
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function render(InvestorService $investors, PayoutService $payouts): View
    {
        $investor = $this->investor();
        $accounts = $investor ? $investor->accounts()->orderBy('currency_code')->get() : collect();
        if ($this->currency === '' && $accounts->isNotEmpty()) {
            $this->currency = $accounts->first()->currency_code;
        }

        return view('livewire.investor.dashboard', [
            'investor' => $investor,
            'accepted' => $investor?->acknowledged(InvestorService::DISCLOSURE_VERSION) ?? false,
            'plans' => $investor?->isApproved() ? $investors->openPlans(auth()->user()) : collect(),
            'investments' => $investor ? $investor->investments()->with(['plan.country', 'allocations.vehicle.vehicleModel.brand'])->latest('id')->get() : collect(),
            'balances' => $accounts->map(fn ($a) => ['currency' => $a->currency_code, 'balance' => $a->balance, 'available' => $payouts->available($a)]),
            'payouts' => PayoutRequest::whereIn('account_id', $accounts->pluck('id'))->with('account')->latest('id')->limit(10)->get(),
            'statuses' => [
                Investment::PENDING => 'bg-amber-50 text-amber-700', Investment::ACTIVE => 'bg-emerald-50 text-emerald-700',
                Investment::CLOSED => 'bg-zinc-100 text-zinc-700', Investment::CANCELLED => 'bg-red-50 text-red-700',
            ],
        ])->title(__('site.investor.title'));
    }
}
