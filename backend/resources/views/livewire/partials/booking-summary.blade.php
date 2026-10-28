@php($money = fn (int $minor) => \App\Support\Money::format($minor, $b->currency_code))
@php($m = $b->vehicle->vehicleModel)
@php($statusColor = match ($b->status->value) {
    'confirmed', 'picked_up', 'completed' => 'bg-emerald-50 text-emerald-700',
    'cancelled' => 'bg-red-50 text-red-700',
    default => 'bg-amber-50 text-amber-700',
})
<div class="mt-4 space-y-5 text-sm">
    <div class="flex items-center gap-4">
        <x-vehicle-image :model="$m" class="aspect-[16/10] w-28 shrink-0 rounded-2xl sm:w-36" />
        <div class="min-w-0">
            <div class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ $m->brand->name }}</div>
            <div class="text-lg font-bold tracking-tight">{{ $m->name }}</div>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <span class="badge {{ $statusColor }}">{{ __('site.status.'.$b->status->value) }}</span>
                <span class="text-zinc-500">#{{ $b->id }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-2xl bg-zinc-50 p-3.5">
            <div class="text-xs text-zinc-500">{{ __('site.search.pick_up') }}</div>
            <div class="font-semibold">{{ $b->start_at->translatedFormat('D j M Y, H:i') }}</div>
        </div>
        <div class="rounded-2xl bg-zinc-50 p-3.5">
            <div class="text-xs text-zinc-500">{{ __('site.search.return') }}</div>
            <div class="font-semibold">{{ $b->end_at->translatedFormat('D j M Y, H:i') }}</div>
        </div>
        <div class="flex items-center gap-2 rounded-2xl bg-zinc-50 p-3.5 sm:col-span-2">
            <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <span class="font-medium">{{ $b->vehicle->branch->name }}</span>
        </div>
    </div>

    @if ($b->addons->isNotEmpty())
        <ul class="flex flex-wrap gap-2">
            @foreach ($b->addons as $line)<li class="badge bg-zinc-100 text-zinc-700">{{ $line->addon->name }} × {{ $line->qty }}</li>@endforeach
        </ul>
    @endif

    <dl class="space-y-2 border-t border-zinc-100 pt-4">
        <div class="flex justify-between"><dt class="text-zinc-600">{{ __('site.vehicle.subtotal') }}</dt><dd>{{ $money($b->subtotal) }}</dd></div>
        @if ($b->discount_total > 0)<div class="flex justify-between text-emerald-700"><dt>{{ __('site.vehicle.discounts') }}</dt><dd>−{{ $money($b->discount_total) }}</dd></div>@endif
        <div class="flex justify-between"><dt class="text-zinc-600">{{ __('site.vehicle.tax') }}</dt><dd>{{ $money($b->tax_total) }}</dd></div>
        <div class="flex justify-between border-t border-zinc-100 pt-2 font-semibold"><dt>{{ __('site.vehicle.total') }}</dt><dd>{{ $money($b->grand_total) }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-zinc-600">{{ __('site.vehicle.deposit') }}<span class="block text-xs text-zinc-400">{{ __('site.vehicle.deposit_note') }}</span></dt><dd>{{ $money($b->deposit_amount) }}</dd></div>
        <div class="flex justify-between rounded-2xl bg-zinc-50 px-3 py-2.5 font-semibold"><dt>{{ __('site.booking.amount_paid') }}</dt><dd>{{ $money($b->amountPaid()) }}</dd></div>
    </dl>
</div>
