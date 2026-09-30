@php($money = fn (int $minor) => \App\Support\Money::format($minor, $b->currency_code))
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2">
        <a href="{{ route('account.bookings') }}" class="text-sm text-zinc-500 hover:underline">← {{ __('site.nav.my_bookings') }}</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ __('site.booking.title', ['id' => $b->id]) }}</h1>
        @include('livewire.partials.booking-summary', ['b' => $b])
        @if ($b->deposit)
            <p class="mt-3 text-sm text-zinc-600">{{ __('site.booking.deposit_status') }}: {{ __('site.deposit.'.$b->deposit->status) }}
                @if ($b->deposit->refunded_amount > 0) ({{ $money($b->deposit->refunded_amount) }}) @endif</p>
        @endif
    </section>

    <aside class="card h-fit space-y-4">
        @if ($notice)<p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ $notice }}</p>@endif
        @if ($actionError)<p class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $actionError }}</p>@endif

        @if ($due > 0 && in_array($b->status->value, ['draft', 'pending_payment', 'confirmed', 'picked_up'], true) && ! $b->isExpiredDraft())
            <div>
                <p class="text-sm">{{ __('site.booking.amount_due') }}: <strong>{{ $money($due) }}</strong></p>
                <a href="{{ route('checkout', $b) }}" class="btn mt-2 w-full">{{ __('site.booking.pay_balance') }}</a>
            </div>
        @endif

        @if ($canExtend)
            <form wire:submit="extend" class="space-y-2">
                <label class="label" for="newEnd">{{ __('site.booking.new_end') }}</label>
                <input id="newEnd" type="datetime-local" wire:model="newEnd" class="field" required>
                <button class="btn-light w-full" wire:loading.attr="disabled">{{ __('site.booking.extend') }}</button>
            </form>
        @endif

        @if ($canCancel)
            <button wire:click="cancel" wire:confirm="{{ __('site.booking.cancel_confirm') }}" wire:loading.attr="disabled" class="btn-light w-full text-red-700">
                {{ __('site.booking.cancel') }}
            </button>
        @endif

        <a href="{{ route('account.bookings.invoice', $b) }}" class="btn-light w-full">{{ __('site.booking.invoice') }}</a>
    </aside>
</div>
