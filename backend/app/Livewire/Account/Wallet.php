<?php

namespace App\Livewire\Account;

use App\Models\Country;
use App\Models\WalletTransaction;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Support\Money;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

// Wallet: the customer's own balances and history (as GET /wallet) and top-up via a configured gateway (as POST /wallet/topup).
#[Layout('layouts.site')]
class Wallet extends Component
{
    use WithPagination;

    public string $amount = '';

    public string $currency = '';

    public ?string $topupError = null;

    public bool $processing = false;

    public function mount(): void
    {
        $this->currency = (string) Country::whereKey(auth()->user()->country_id)->value('currency_code');
    }

    public function topup(PaymentService $payments, string $gateway): void
    {
        $this->topupError = null;
        $this->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'size:3', Rule::exists('countries', 'currency_code')->where('is_active', true)->whereNull('deleted_at')],
        ]);
        $minor = Money::toMinor($this->amount, $this->currency);
        if ($minor < 1 || $minor > 100000000 || ! in_array($gateway, $payments->gatewaysFor($this->currency), true)) {
            $this->topupError = $minor < 1 || $minor > 100000000 ? __('site.wallet.amount_invalid') : __('messages.payment_gateway_unavailable');

            return;
        }

        try {
            ['client' => $client] = $payments->createTopup(auth()->user(), $minor, $this->currency, $gateway);
        } catch (DomainException $e) {
            $this->topupError = $e->getMessage();

            return;
        }

        // The balance changes only when the gateway's webhook reports success.
        $this->processing = true;
        $this->dispatch('start-card-payment', gateway: $gateway, client: $client, returnUrl: route('account.wallet'), name: config('app.name'));
    }

    public function render(WalletService $wallet, PaymentService $payments): View
    {
        $userId = auth()->id();

        return view('livewire.account.wallet', [
            'balances' => $wallet->balances($userId),
            'transactions' => WalletTransaction::where('user_id', $userId)->latest('id')->paginate(15),
            'cardsConfigured' => $payments->configuredGateways() !== [],
            'gateways' => $this->currency ? $payments->gatewaysFor($this->currency) : [],
            'currencies' => Country::active()->orderBy('currency_code')->distinct()->pluck('currency_code'),
        ])->title(__('site.nav.wallet'));
    }
}
