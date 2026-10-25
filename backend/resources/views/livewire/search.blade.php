<div>
    <section class="relative mx-[calc(50%-50vw)] -mt-8 mb-10 overflow-hidden bg-gradient-to-br from-brand-900 via-brand-700 to-brand-500 px-4 pt-12 pb-40 text-white sm:pt-16 sm:pb-44">
        <div class="absolute -end-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-32 start-1/3 h-72 w-72 rounded-full bg-amber-400/20 blur-3xl"></div>
        <svg viewBox="0 0 400 160" aria-hidden="true" class="pointer-events-none absolute end-4 bottom-6 hidden w-[28rem] text-white/15 lg:block rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="70" cy="120" r="30"/><circle cx="200" cy="120" r="30"/><path d="M70 120l45-42h50l35 42M115 78l-12-20h-14M165 78l20-30h18"/>
            <path d="M240 110v-18q0-10 12-12l40-6 30-24q8-6 18-6h20q10 0 18 8l20 24" /><circle cx="285" cy="122" r="16"/><circle cx="370" cy="122" r="16"/>
        </svg>
        <div class="relative mx-auto max-w-6xl">
            <h1 class="max-w-2xl text-3xl font-bold tracking-tight sm:text-5xl">{{ __('site.search.title') }}</h1>
            <p class="mt-3 max-w-xl text-base text-white/80 sm:text-lg">{{ __('site.search.subtitle') }}</p>
        </div>
    </section>

    <form wire:submit="$refresh" class="card relative z-10 -mt-32 mb-8 space-y-5 sm:-mt-36">
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('site.search.type') }}">
            @foreach (['' => __('site.search.any'), 'bike' => __('site.search.bike'), 'car' => __('site.search.car')] as $value => $label)
                <button type="button" wire:click="$set('type', {{ $value === '' ? 'null' : "'$value'" }})"
                        class="chip {{ (string) $type === $value ? 'chip-on' : '' }}" aria-pressed="{{ (string) $type === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="me-1 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ __('site.search.quick') }}</span>
            @foreach ([1, 3, 7] as $days)
                <button type="button" wire:click="preset({{ $days }})" class="chip py-1">{{ $days === 7 ? __('site.search.week') : trans_choice('site.search.days', $days, ['n' => $days]) }}</button>
            @endforeach
        </div>
        <div class="grid grid-cols-1 gap-4 border-t border-zinc-100 pt-5 sm:grid-cols-3">
            <div>
                <label class="label" for="category">{{ __('site.search.category') }}</label>
                <select id="category" wire:model.live="category" class="field">
                    <option value="">{{ __('site.search.any') }}</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="seats">{{ __('site.search.seats') }}</label>
                <input id="seats" type="number" min="1" wire:model.live.debounce.500ms="seats" class="field">
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn w-full py-3">
                    <svg wire:loading.remove viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <svg wire:loading viewBox="0 0 24 24" class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" stroke-linecap="round"/></svg>
                    {{ __('site.search.button') }}
                </button>
            </div>
        </div>
    </form>

    <div wire:loading.delay class="mb-4 text-sm text-zinc-500">{{ __('site.common.loading') }}</div>

    @if ($vehicles === null)
        <p class="card text-zinc-600">{{ __('site.search.dates_invalid') }}</p>
    @elseif ($vehicles->isEmpty())
        <div class="card flex flex-col items-center gap-3 py-12 text-center text-zinc-600">
            <svg viewBox="0 0 24 24" class="h-10 w-10 text-zinc-300" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M8 11h6" stroke-linecap="round"/></svg>
            {{ __('site.search.no_results') }}
        </div>
    @else
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @foreach ($vehicles as $vehicle)
                @php($m = $vehicle->vehicleModel)
                <a wire:key="v{{ $vehicle->id }}" href="{{ route('vehicles.show', ['id' => $vehicle->id, 'from' => $from, 'to' => $to]) }}"
                   class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-brand-900/10">
                    <x-vehicle-image :model="$m">
                        <span class="badge absolute start-3 top-3 bg-white/90 text-zinc-700 shadow-sm backdrop-blur">{{ $m->category->name }} · {{ __('site.search.'.$m->category->type) }}</span>
                    </x-vehicle-image>
                    <div class="flex flex-1 flex-col gap-2 p-5">
                        <div class="text-lg font-semibold">{{ $m->brand->name }} {{ $m->name }}</div>
                        <div class="flex flex-wrap gap-1.5 text-xs text-zinc-600">
                            <span class="badge bg-zinc-100">{{ trans_choice('site.vehicle.seats', (int) $m->seats, ['n' => (int) $m->seats]) }}</span>
                            @if ($m->transmission)<span class="badge bg-zinc-100">{{ $m->transmission }}</span>@endif
                            @if ($m->fuel_type)<span class="badge bg-zinc-100">{{ $m->fuel_type }}</span>@endif
                        </div>
                        <div class="flex items-center gap-1 text-sm text-zinc-500">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            {{ $vehicle->branch->name }}
                        </div>
                        <div class="mt-auto flex items-end justify-between gap-3 border-t border-zinc-100 pt-3 text-sm">
                            @if ($prices->has($vehicle->id))
                                @if ($prices[$vehicle->id])
                                    <span><span class="block text-xl font-bold text-zinc-900">{{ \App\Support\Money::format($prices[$vehicle->id]['amount'], $prices[$vehicle->id]['currency']) }}</span>
                                    <span class="text-xs text-zinc-500">{{ __('site.search.total_for_dates') }}</span></span>
                                @else
                                    <span class="text-zinc-500">{{ __('site.vehicle.price_unavailable') }}</span>
                                @endif
                            @else
                                <span class="text-zinc-500">{{ __('site.search.pick_dates_for_price') }}</span>
                            @endif
                            <span class="shrink-0 font-semibold text-brand-600 group-hover:underline">{{ __('site.search.from_price') }} <span class="inline-block rtl:rotate-180">→</span></span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-8">{{ $vehicles->links() }}</div>
    @endif
</div>
