<div class="mx-auto max-w-2xl" @if ($state === 'waiting') wire:poll.3s @endif>
    <div class="card">
        @if ($state === 'confirmed')
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/30">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </span>
                <p class="text-base font-semibold text-emerald-700">{{ __('site.confirmation.confirmed') }}</p>
            </div>
        @elseif ($state === 'failed')
            <p class="text-sm font-semibold text-red-700">{{ __('site.confirmation.failed') }}</p>
        @else
            <p class="text-sm font-semibold text-amber-700">{{ __('site.confirmation.waiting') }}</p>
            @if (request()->query('redirect_status') === 'failed')
                <p class="mt-1 text-sm text-red-700">{{ __('site.confirmation.card_failed') }}</p>
            @endif
        @endif
        <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ __('site.confirmation.title') }}</h1>
        @include('livewire.partials.booking-summary', ['b' => $b])
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('account.bookings.show', $b) }}" class="btn">{{ __('site.confirmation.view_booking') }}</a>
            @if ($state === 'waiting')<a href="{{ route('checkout', $b) }}" class="btn-light">{{ __('site.checkout.title') }}</a>@endif
        </div>
    </div>
</div>
