@php($m = $vehicle->vehicleModel)
@php($money = fn (int $minor) => \App\Support\Money::format($minor, $quote['currency_code'] ?? 'USD'))
@php($bookLabel = auth()->check() ? __('site.vehicle.book_now') : __('site.vehicle.login_to_book'))
<div class="pb-24 lg:pb-0">
    <a href="{{ route('home', ['from' => $from, 'to' => $to]) }}" class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-zinc-600 hover:text-zinc-900"><span class="rtl:rotate-180">←</span> {{ __('site.common.back') }}</a>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            <section class="overflow-hidden rounded-3xl bg-white shadow-[0_12px_32px_-16px_rgb(15_23_42/.18)] ring-1 ring-zinc-200/70">
                <x-vehicle-image :model="$m" class="aspect-[16/10]" />
                <div class="p-5 sm:p-7">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                        <span class="text-brand-600">{{ $m->category->name }}</span><span class="text-zinc-300">•</span><span class="text-zinc-500">{{ __('site.search.'.$m->category->type) }}</span>
                    </div>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl"><span class="text-zinc-500">{{ $m->brand->name }}</span> {{ $m->name }}</h1>

                    <h2 class="mt-6 text-sm font-semibold text-zinc-900">{{ __('site.vehicle.specs') }}</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        @foreach ([
                            [__('site.vehicle.seats_label'), $m->seats, '<circle cx="12" cy="8" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/>'],
                            [__('site.vehicle.transmission'), $m->transmission, '<circle cx="6" cy="6" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="6" cy="18" r="2"/><path d="M6 8v8M18 8v2a2 2 0 0 1-2 2H6"/>'],
                            [__('site.vehicle.fuel'), $m->fuel_type, '<path d="M4 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h12M14 9h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V8l-3-3M7 8h4"/>'],
                            [__('site.vehicle.type'), $m->category->name, '<rect x="3" y="7" width="18" height="10" rx="3"/><path d="M7 17v2M17 17v2"/>'],
                        ] as [$label, $value, $icon])
                            <div class="rounded-2xl bg-zinc-50 p-3.5">
                                <svg viewBox="0 0 24 24" class="h-5 w-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                <dt class="mt-2 text-xs text-zinc-500">{{ $label }}</dt>
                                <dd class="font-semibold capitalize">{{ $value ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <h2 class="mt-6 text-sm font-semibold text-zinc-900">{{ __('site.vehicle.pickup_return') }}</h2>
                    <div class="mt-3 flex items-start gap-3 rounded-2xl bg-zinc-50 p-4 text-sm">
                        <svg viewBox="0 0 24 24" class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        <div>
                            <div class="font-semibold">{{ $vehicle->branch->name }}</div>
                            <div class="text-zinc-500">{{ collect([$vehicle->branch->location?->name, $vehicle->branch->location?->city?->name])->filter()->join(', ') }}</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Booking panel: dates, price breakdown and the button in one place; sticky on desktop. --}}
        <aside class="card h-fit space-y-5 lg:sticky lg:top-24">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="from">{{ __('site.search.pick_up') }}</label>
                    <input id="from" type="datetime-local" wire:model.live.debounce.500ms="from" class="field px-2.5">
                </div>
                <div>
                    <label class="label" for="to">{{ __('site.search.return') }}</label>
                    <input id="to" type="datetime-local" wire:model.live.debounce.500ms="to" class="field px-2.5">
                </div>
            </div>
            @if ($periodError)<p class="error">{{ $periodError }}</p>@endif

            <div wire:loading.remove wire:target="from,to">
                @if ($available === true)
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('site.vehicle.available') }}</p>
                @elseif ($available === false)
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-700"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>{{ __('site.vehicle.unavailable') }}</p>
                @endif
            </div>

            <div>
                <h2 class="text-base font-bold">{{ __('site.vehicle.quote') }}</h2>
                <div wire:loading class="mt-2 text-sm text-zinc-500">{{ __('site.common.loading') }}</div>
                @if ($quoteError)
                    <p class="error">{{ $quoteError }}</p>
                @elseif ($quote)
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-zinc-600">{{ __('site.vehicle.subtotal') }} · {{ $quote['unit'] === 'day' ? trans_choice('site.search.days', $quote['units'], ['n' => $quote['units']]) : trans_choice('site.vehicle.hours', $quote['units'], ['n' => $quote['units']]) }}</dt><dd>{{ $money($quote['subtotal']) }}</dd></div>
                        @if ($quote['discount_total'] > 0)<div class="flex justify-between text-emerald-700"><dt>{{ __('site.vehicle.discounts') }}</dt><dd>−{{ $money($quote['discount_total']) }}</dd></div>@endif
                        <div class="flex justify-between"><dt class="text-zinc-600">{{ __('site.vehicle.tax') }}</dt><dd>{{ $money($quote['tax_total']) }}</dd></div>
                        <div class="flex items-baseline justify-between border-t border-zinc-100 pt-2 font-semibold"><dt>{{ __('site.vehicle.total') }}</dt><dd>{{ $money($quote['grand_total']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-zinc-600">{{ __('site.vehicle.deposit') }}<span class="block text-xs text-zinc-400">{{ __('site.vehicle.deposit_note') }}</span></dt><dd>{{ $money($quote['deposit_amount']) }}</dd></div>
                        <div class="flex items-baseline justify-between rounded-2xl bg-brand-50 px-3 py-3 font-bold text-brand-900"><dt>{{ __('site.vehicle.due_now') }}</dt><dd class="text-2xl tracking-tight">{{ $money($quote['grand_total'] + $quote['deposit_amount']) }}</dd></div>
                    </dl>
                @else
                    <p class="mt-2 text-sm text-zinc-500">{{ __('site.search.pick_dates_for_price') }}</p>
                @endif
            </div>

            <details class="group rounded-2xl border border-zinc-200 px-4 py-3" @if ($coupon !== '' || array_filter($qty)) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold">{{ __('site.vehicle.extras') }}<span class="text-zinc-400 transition group-open:rotate-180">⌄</span></summary>
                @if ($addonList->isNotEmpty())
                    <div class="mt-2 divide-y divide-zinc-100">
                        @foreach ($addonList as $addon)
                            <label wire:key="a{{ $addon->id }}" class="flex items-center justify-between gap-4 py-2 text-sm">
                                <span>{{ $addon->name }} <span class="text-zinc-500">({{ __('site.vehicle.addon_'.$addon->type) }})</span></span>
                                <input type="number" min="0" max="100" wire:model.live.debounce.400ms="qty.{{ $addon->id }}" class="field w-20 py-1.5" aria-label="{{ __('site.vehicle.qty') }}">
                            </label>
                        @endforeach
                    </div>
                @endif
                <div class="mt-3">
                    <label class="label" for="coupon">{{ __('site.vehicle.coupon') }}</label>
                    <input id="coupon" type="text" wire:model.live.debounce.600ms="coupon" class="field" autocomplete="off">
                </div>
            </details>

            @if ($bookingError)<p class="error">{{ $bookingError }}</p>@endif
            <button wire:click="book" wire:loading.attr="disabled" class="btn w-full py-3.5 text-base" @disabled(! $quote || $available === false)>
                <svg wire:loading wire:target="book" viewBox="0 0 24 24" class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" stroke-linecap="round"/></svg>
                {{ $bookLabel }}
            </button>
        </aside>
    </div>

    {{-- Phones: total and the button stay on screen. --}}
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
            <div class="min-w-0">
                @if ($quote)
                    <div class="text-lg font-extrabold tracking-tight">{{ $money($quote['grand_total'] + $quote['deposit_amount']) }}</div>
                    <div class="truncate text-xs text-zinc-500">{{ __('site.vehicle.due_now') }}</div>
                @else
                    <div class="text-sm text-zinc-500">{{ __('site.search.pick_dates_for_price') }}</div>
                @endif
            </div>
            <button wire:click="book" wire:loading.attr="disabled" class="btn shrink-0 py-3" @disabled(! $quote || $available === false)>{{ $bookLabel }}</button>
        </div>
    </div>
</div>
