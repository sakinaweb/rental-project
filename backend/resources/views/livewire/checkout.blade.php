@php($money = fn (int $minor) => \App\Support\Money::format($minor, $b->currency_code))
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.checkout.title') }}</h1>
        @include('livewire.partials.booking-summary', ['b' => $b])
    </section>

    <aside class="card h-fit lg:sticky lg:top-24" x-data="cardPayment" x-on:start-card-payment.window="start($event.detail)">
        <h2 class="text-base font-bold">{{ __('site.checkout.pay') }}</h2>
        <div class="mt-3 rounded-2xl bg-brand-50 px-4 py-3 text-brand-900">
            <div class="text-xs font-semibold tracking-wide uppercase">{{ __('site.booking.amount_due') }}</div>
            <div class="text-3xl font-extrabold tracking-tight">{{ $money(max(0, $due)) }}</div>
        </div>

        @if ($expired)
            <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ __('messages.booking_draft_expired') }}</p>
            <a href="{{ route('vehicles.show', ['id' => $b->vehicle_id]) }}" class="btn-light mt-3 w-full">{{ __('site.common.back') }}</a>
        @elseif (! $payable || $due <= 0)
            <a href="{{ route('confirmation', $b) }}" class="btn mt-4 w-full">{{ __('site.confirmation.title') }}</a>
        @else
            @if ($b->status->value === 'draft')
                <p class="mt-1 text-xs text-zinc-500">{{ __('site.checkout.hold_note', ['time' => $b->created_at->addMinutes(\App\Models\Booking::draftTtlMinutes())->format('H:i')]) }}</p>
            @endif
            @if ($payError)<p class="error">{{ $payError }}</p>@endif

            <div class="mt-4 space-y-3">
                <button wire:click="payWithWallet" wire:loading.attr="disabled" class="btn w-full" @disabled($walletBalance < $due)>
                    {{ __('site.checkout.pay_wallet') }} ({{ $money($walletBalance) }})
                </button>
                @if ($walletBalance < $due)<p class="text-xs text-zinc-500">{{ __('messages.wallet_insufficient') }}</p>@endif

                @foreach ($gateways as $gateway)
                    <button wire:click="payWithCard('{{ $gateway }}')" wire:loading.attr="disabled" class="btn-light w-full py-3">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h3"/></svg>
                        {{ __('site.checkout.pay_'.$gateway) }}
                    </button>
                @endforeach
                @if (empty($gateways))<p class="text-xs text-zinc-500">{{ __('site.checkout.no_card') }}</p>@endif
            </div>

            <div x-show="gateway === 'stripe'" x-cloak class="mt-4 space-y-3">
                <div x-ref="stripeElement"></div>
                <button type="button" class="btn w-full" x-on:click="confirmStripe" x-bind:disabled="busy">{{ __('site.checkout.pay') }}</button>
            </div>
            <p x-show="error" x-text="error" class="error"></p>
        @endif
    </aside>
</div>
