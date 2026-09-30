<div>
    <h1 class="mb-6 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('site.search.title') }}</h1>

    <form wire:submit="$refresh" class="card mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="label" for="country">{{ __('site.search.country') }}</label>
            <select id="country" wire:model.live="country_id" class="field">
                <option value="">{{ __('site.search.any') }}</option>
                @foreach ($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="city">{{ __('site.search.city') }}</label>
            <select id="city" wire:model.live="city_id" class="field" @disabled($cities->isEmpty())>
                <option value="">{{ __('site.search.any') }}</option>
                @foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="from">{{ __('site.search.pick_up') }}</label>
            <input id="from" type="datetime-local" wire:model.live.debounce.500ms="from" class="field">
        </div>
        <div>
            <label class="label" for="to">{{ __('site.search.return') }}</label>
            <input id="to" type="datetime-local" wire:model.live.debounce.500ms="to" class="field">
            @if ($searchErrors->has('to') || $searchErrors->has('from'))<p class="error">{{ __('site.search.dates_invalid') }}</p>@endif
        </div>
        <div>
            <label class="label" for="category">{{ __('site.search.category') }}</label>
            <select id="category" wire:model.live="category" class="field">
                <option value="">{{ __('site.search.any') }}</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="type">{{ __('site.search.type') }}</label>
            <select id="type" wire:model.live="type" class="field">
                <option value="">{{ __('site.search.any') }}</option>
                <option value="bike">{{ __('site.search.bike') }}</option>
                <option value="car">{{ __('site.search.car') }}</option>
            </select>
        </div>
        <div>
            <label class="label" for="seats">{{ __('site.search.seats') }}</label>
            <input id="seats" type="number" min="1" wire:model.live.debounce.500ms="seats" class="field">
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn w-full">{{ __('site.search.button') }}</button>
        </div>
    </form>

    <div wire:loading.delay class="mb-4 text-sm text-zinc-500">{{ __('site.common.loading') }}</div>

    @if ($vehicles === null)
        <p class="card text-zinc-600">{{ __('site.search.dates_invalid') }}</p>
    @elseif ($vehicles->isEmpty())
        <p class="card text-zinc-600">{{ __('site.search.no_results') }}</p>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @foreach ($vehicles as $vehicle)
                <a wire:key="v{{ $vehicle->id }}" href="{{ route('vehicles.show', ['id' => $vehicle->id, 'from' => $from, 'to' => $to]) }}"
                   class="card flex flex-col gap-2 transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">{{ $vehicle->vehicleModel->category->name }} · {{ __('site.search.'.$vehicle->vehicleModel->category->type) }}</div>
                    <div class="text-lg font-semibold">{{ $vehicle->vehicleModel->brand->name }} {{ $vehicle->vehicleModel->name }}</div>
                    <div class="text-sm text-zinc-600">
                        {{ trans_choice('site.vehicle.seats', (int) $vehicle->vehicleModel->seats, ['n' => (int) $vehicle->vehicleModel->seats]) }}
                        @if ($vehicle->vehicleModel->transmission) · {{ $vehicle->vehicleModel->transmission }} @endif
                        @if ($vehicle->vehicleModel->fuel_type) · {{ $vehicle->vehicleModel->fuel_type }} @endif
                    </div>
                    <div class="text-sm text-zinc-600">{{ $vehicle->branch->name }}</div>
                    <div class="mt-auto pt-2 text-sm">
                        @if ($prices->has($vehicle->id))
                            @if ($prices[$vehicle->id])
                                <span class="text-lg font-bold">{{ \App\Support\Money::format($prices[$vehicle->id]['amount'], $prices[$vehicle->id]['currency']) }}</span>
                                <span class="text-zinc-500">{{ __('site.search.total_for_dates') }}</span>
                            @else
                                <span class="text-zinc-500">{{ __('site.vehicle.price_unavailable') }}</span>
                            @endif
                        @else
                            <span class="text-zinc-500">{{ __('site.search.pick_dates_for_price') }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $vehicles->links() }}</div>
    @endif
</div>
