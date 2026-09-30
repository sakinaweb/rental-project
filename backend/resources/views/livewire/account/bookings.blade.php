<div>
    <h1 class="mb-6 text-2xl font-bold tracking-tight">{{ __('site.nav.my_bookings') }}</h1>
    @if ($bookings->isEmpty())
        <div class="card text-zinc-600">{{ __('site.booking.none') }} <a href="{{ route('home') }}" class="font-semibold underline">{{ __('site.nav.search') }}</a></div>
    @else
        <div class="space-y-3">
            @foreach ($bookings as $b)
                <a wire:key="b{{ $b->id }}" href="{{ route('account.bookings.show', $b) }}" class="card flex flex-col gap-1 hover:shadow-md sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-semibold">{{ $b->vehicle->vehicleModel->brand->name }} {{ $b->vehicle->vehicleModel->name }} <span class="text-sm font-normal text-zinc-500">#{{ $b->id }}</span></div>
                        <div class="text-sm text-zinc-600">{{ $b->start_at->translatedFormat('j M Y, H:i') }} → {{ $b->end_at->translatedFormat('j M Y, H:i') }}</div>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium">{{ __('site.status.'.$b->status->value) }}</span>
                        <span class="font-semibold">{{ \App\Support\Money::format($b->grand_total, $b->currency_code) }}</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $bookings->links() }}</div>
    @endif
</div>
