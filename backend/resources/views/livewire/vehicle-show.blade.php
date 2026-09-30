@php($m = $vehicle->vehicleModel)
@php($money = fn (int $minor) => \App\Support\Money::format($minor, $quote['currency_code'] ?? 'USD'))
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2">
        <a href="{{ route('home', ['from' => $from, 'to' => $to]) }}" class="text-sm text-zinc-500 hover:underline">← {{ __('site.common.back') }}</a>
        <div class="mt-3 text-xs uppercase tracking-wide text-zinc-500">{{ $m->category->name }} · {{ __('site.search.'.$m->category->type) }}</div>
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $m->brand->name }} {{ $m->name }}</h1>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-zinc-500">{{ __('site.vehicle.seats_label') }}</dt><dd class="font-medium">{{ $m->seats ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('site.vehicle.transmission') }}</dt><dd class="font-medium">{{ $m->transmission ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('site.vehicle.fuel') }}</dt><dd class="font-medium">{{ $m->fuel_type ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('site.vehicle.branch') }}</dt><dd class="font-medium">{{ $vehicle->branch->name }}</dd></div>
        </dl>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="from">{{ __('site.search.pick_up') }}</label>
                <input id="from" type="datetime-local" wire:model.live.debounce.500ms="from" class="field">
            </div>
            <div>
                <label class="label" for="to">{{ __('site.search.return') }}</label>
                <input id="to" type="datetime-local" wire:model.live.debounce.500ms="to" class="field">
            </div>
        </div>
        @if ($periodError)<p class="error">{{ $periodError }}</p>@endif

        <div class="mt-4" wire:loading.remove wire:target="from,to">
            @if ($available === true)
                <p class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">{{ __('site.vehicle.available') }}</p>
            @elseif ($available === false)
                <p class="inline-flex rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-700">{{ __('site.vehicle.unavailable') }}</p>
            @else
                <p class="text-sm text-zinc-500">{{ __('site.search.pick_dates_for_price') }}</p>
            @endif
        </div>

        @if ($addonList->isNotEmpty())
            <h2 class="mt-8 font-semibold">{{ __('site.vehicle.addons') }}</h2>
            <div class="mt-2 divide-y divide-zinc-100">
                @foreach ($addonList as $addon)
                    <label wire:key="a{{ $addon->id }}" class="flex items-center justify-between gap-4 py-2 text-sm">
                        <span>{{ $addon->name }} <span class="text-zinc-500">({{ __('site.vehicle.addon_'.$addon->type) }})</span></span>
                        <input type="number" min="0" max="100" wire:model.live.debounce.400ms="qty.{{ $addon->id }}" class="field w-20" aria-label="{{ __('site.vehicle.qty') }}">
                    </label>
                @endforeach
            </div>
        @endif

        <div class="mt-6 max-w-sm">
            <label class="label" for="coupon">{{ __('site.vehicle.coupon') }}</label>
            <input id="coupon" type="text" wire:model.live.debounce.600ms="coupon" class="field" autocomplete="off">
        </div>
    </section>

    <aside class="card h-fit lg:sticky lg:top-6">
        <h2 class="font-semibold">{{ __('site.vehicle.quote') }}</h2>
        <div wire:loading class="mt-2 text-sm text-zinc-500">{{ __('site.common.loading') }}</div>
        @if ($quoteError)
            <p class="error">{{ $quoteError }}</p>
        @elseif ($quote)
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between"><dt>{{ __('site.vehicle.subtotal') }}</dt><dd>{{ $money($quote['subtotal']) }}</dd></div>
                @if ($quote['discount_total'] > 0)<div class="flex justify-between text-emerald-700"><dt>{{ __('site.vehicle.discounts') }}</dt><dd>−{{ $money($quote['discount_total']) }}</dd></div>@endif
                <div class="flex justify-between"><dt>{{ __('site.vehicle.tax') }}</dt><dd>{{ $money($quote['tax_total']) }}</dd></div>
                <div class="flex justify-between border-t border-zinc-200 pt-2 font-semibold"><dt>{{ __('site.vehicle.total') }}</dt><dd>{{ $money($quote['grand_total']) }}</dd></div>
                <div class="flex justify-between text-zinc-600"><dt>{{ __('site.vehicle.deposit') }}</dt><dd>{{ $money($quote['deposit_amount']) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>{{ __('site.vehicle.due_now') }}</dt><dd>{{ $money($quote['grand_total'] + $quote['deposit_amount']) }}</dd></div>
            </dl>
        @else
            <p class="mt-2 text-sm text-zinc-500">{{ __('site.search.pick_dates_for_price') }}</p>
        @endif

        @if ($bookingError)<p class="error">{{ $bookingError }}</p>@endif
        <button wire:click="book" wire:loading.attr="disabled" class="btn mt-4 w-full" @disabled(! $quote || $available === false)>
            {{ auth()->check() ? __('site.vehicle.book_now') : __('site.vehicle.login_to_book') }}
        </button>
    </aside>
</div>
