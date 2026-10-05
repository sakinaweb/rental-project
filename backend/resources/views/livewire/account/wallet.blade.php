<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="space-y-6 lg:col-span-2">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.nav.wallet') }}</h1>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @forelse ($balances as $currency => $balance)
                <div class="card"><div class="text-xs text-zinc-500">{{ __('site.wallet.balance') }} · {{ $currency }}</div><div class="text-xl font-bold">{{ \App\Support\Money::format($balance, $currency) }}</div></div>
            @empty
                <div class="card sm:col-span-3 text-zinc-600">{{ __('site.wallet.empty') }}</div>
            @endforelse
        </div>

        <div class="card overflow-x-auto p-0">
            @if ($transactions->isEmpty())
                <p class="p-5 text-sm text-zinc-600">{{ __('site.wallet.no_transactions') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-start text-zinc-500"><tr>
                        <th class="p-3 text-start">{{ __('site.wallet.date') }}</th><th class="p-3 text-start">{{ __('site.wallet.type') }}</th>
                        <th class="p-3 text-end">{{ __('site.wallet.amount') }}</th><th class="p-3 text-end">{{ __('site.wallet.balance') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($transactions as $t)
                            <tr wire:key="t{{ $t->id }}" class="border-t border-zinc-100">
                                <td class="p-3">{{ $t->created_at->translatedFormat('j M Y, H:i') }}</td>
                                <td class="p-3">{{ __('site.wallet.type_'.$t->type) }}</td>
                                <td @class(['p-3 text-end font-medium', 'text-emerald-700' => $t->amount > 0])>{{ \App\Support\Money::format($t->amount, $t->currency_code) }}</td>
                                <td class="p-3 text-end">{{ \App\Support\Money::format($t->balance_after, $t->currency_code) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <div>{{ $transactions->links() }}</div>
    </section>

    <aside class="card h-fit space-y-3" x-data="cardPayment" x-on:start-card-payment.window="start($event.detail)">
        <h2 class="font-semibold">{{ __('site.wallet.top_up') }}</h2>
        @unless ($cardsConfigured)
            <p class="text-sm text-zinc-500">{{ __('site.checkout.no_card') }}</p>
        @else
            <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                    <label class="label" for="amount">{{ __('site.wallet.amount') }}</label>
                    <input id="amount" type="number" step="0.01" min="0" wire:model="amount" class="field">
                </div>
                <div>
                    <label class="label" for="currency">{{ __('site.wallet.currency') }}</label>
                    <select id="currency" wire:model.live="currency" class="field">
                        <option value="">—</option>
                        @foreach ($currencies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                </div>
            </div>
            @error('amount')<p class="error">{{ $message }}</p>@enderror
            @error('currency')<p class="error">{{ $message }}</p>@enderror
            @if ($topupError)<p class="error">{{ $topupError }}</p>@endif
            @if ($processing)<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ __('site.wallet.processing') }}</p>@endif
            @forelse ($gateways as $gateway)
                <button wire:click="topup('{{ $gateway }}')" wire:loading.attr="disabled" class="btn w-full">{{ __('site.checkout.pay_'.$gateway) }}</button>
            @empty
                @if ($currency)<p class="text-sm text-zinc-500">{{ __('site.checkout.no_card') }}</p>@endif
            @endforelse
            <div x-show="gateway === 'stripe'" x-cloak class="space-y-3">
                <div x-ref="stripeElement"></div>
                <button type="button" class="btn w-full" x-on:click="confirmStripe" x-bind:disabled="busy">{{ __('site.checkout.pay') }}</button>
            </div>
            <p x-show="error" x-text="error" class="error"></p>
        @endunless
    </aside>
</div>
