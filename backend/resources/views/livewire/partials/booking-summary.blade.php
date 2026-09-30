@php($money = fn (int $minor) => \App\Support\Money::format($minor, $b->currency_code))
<div class="mt-4 space-y-4 text-sm">
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-semibold">{{ $b->vehicle->vehicleModel->brand->name }} {{ $b->vehicle->vehicleModel->name }}</span>
        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium">{{ __('site.status.'.$b->status->value) }}</span>
        <span class="text-zinc-500">#{{ $b->id }}</span>
    </div>
    <div class="text-zinc-600">{{ $b->vehicle->branch->name }}</div>
    <div>{{ $b->start_at->translatedFormat('D j M Y, H:i') }} → {{ $b->end_at->translatedFormat('D j M Y, H:i') }}</div>
    @if ($b->addons->isNotEmpty())
        <ul class="list-inside list-disc text-zinc-600">
            @foreach ($b->addons as $line)<li>{{ $line->addon->name }} × {{ $line->qty }}</li>@endforeach
        </ul>
    @endif
    <dl class="max-w-sm space-y-1">
        <div class="flex justify-between"><dt>{{ __('site.vehicle.subtotal') }}</dt><dd>{{ $money($b->subtotal) }}</dd></div>
        @if ($b->discount_total > 0)<div class="flex justify-between text-emerald-700"><dt>{{ __('site.vehicle.discounts') }}</dt><dd>−{{ $money($b->discount_total) }}</dd></div>@endif
        <div class="flex justify-between"><dt>{{ __('site.vehicle.tax') }}</dt><dd>{{ $money($b->tax_total) }}</dd></div>
        <div class="flex justify-between border-t border-zinc-200 pt-1 font-semibold"><dt>{{ __('site.vehicle.total') }}</dt><dd>{{ $money($b->grand_total) }}</dd></div>
        <div class="flex justify-between text-zinc-600"><dt>{{ __('site.vehicle.deposit') }}</dt><dd>{{ $money($b->deposit_amount) }}</dd></div>
        <div class="flex justify-between text-zinc-600"><dt>{{ __('site.booking.amount_paid') }}</dt><dd>{{ $money($b->amountPaid()) }}</dd></div>
    </dl>
</div>
