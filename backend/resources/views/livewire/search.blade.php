<div>
    {{-- Hero with the search bar: what, when, where. --}}
    <section class="relative mx-[calc(50%-50vw)] -mt-8 mb-8 overflow-hidden bg-gradient-to-br from-slate-950 via-brand-900 to-brand-700 px-4 pt-12 pb-36 text-white sm:pt-16 sm:pb-40">
        <div class="absolute -end-24 -top-24 h-80 w-80 rounded-full bg-brand-500/30 blur-3xl"></div>
        <div class="absolute -bottom-32 start-1/4 h-72 w-72 rounded-full bg-amber-400/15 blur-3xl"></div>
        <div class="relative mx-auto max-w-6xl">
            <h1 class="max-w-2xl text-3xl font-extrabold tracking-tight sm:text-5xl">{{ __('site.search.title') }}</h1>
            <p class="mt-3 max-w-xl text-base text-white/75 sm:text-lg">{{ __('site.search.subtitle') }}</p>
        </div>
    </section>

    <form wire:submit="$refresh" class="card relative z-10 -mt-32 mb-8 space-y-4 sm:-mt-36">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto] lg:items-end">
            <div>
                <label class="label" for="q">{{ __('site.search.button') }}</label>
                <div class="relative">
                    <svg viewBox="0 0 24 24" class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input id="q" type="search" wire:model.live.debounce.400ms="q" class="field ps-9" placeholder="{{ __('site.search.search_placeholder') }}" maxlength="60">
                </div>
            </div>
            <div>
                <label class="label" for="from">{{ __('site.search.pick_up') }}</label>
                <input id="from" type="datetime-local" wire:model.live.debounce.500ms="from" class="field">
            </div>
            <div>
                <label class="label" for="to">{{ __('site.search.return') }}</label>
                <input id="to" type="datetime-local" wire:model.live.debounce.500ms="to" class="field">
            </div>
            <div>
                <label class="label" for="city">{{ __('site.search.city') }}</label>
                <div class="flex gap-2">
                    <select id="country" wire:model.live="country_id" class="field" aria-label="{{ __('site.search.country') }}">
                        <option value="">{{ __('site.search.country') }}</option>
                        @foreach ($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @if ($cities->isNotEmpty())
                        <select id="city" wire:model.live="city_id" class="field">
                            <option value="">{{ __('site.search.any') }}</option>
                            @foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    @endif
                </div>
            </div>
            <button type="submit" class="btn py-3 sm:col-span-2 lg:col-span-1">
                <svg wire:loading.remove viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <svg wire:loading viewBox="0 0 24 24" class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" stroke-linecap="round"/></svg>
                {{ __('site.search.button') }}
            </button>
        </div>
        @if ($searchErrors->has('to') || $searchErrors->has('from'))<p class="error">{{ __('site.search.dates_invalid') }}</p>@endif
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="me-1 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ __('site.search.quick') }}</span>
            @foreach ([1, 3, 7] as $days)
                <button type="button" wire:click="preset({{ $days }})" class="chip py-1">{{ $days === 7 ? __('site.search.week') : trans_choice('site.search.days', $days, ['n' => $days]) }}</button>
            @endforeach
        </div>
    </form>

    {{-- Toolbar: type and category chips, then price, seats and sorting. --}}
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:px-0" role="group" aria-label="{{ __('site.search.type') }}">
            @foreach (['' => __('site.search.all'), 'bike' => __('site.search.bike'), 'car' => __('site.search.car')] as $value => $label)
                <button type="button" wire:click="$set('type', {{ $value === '' ? 'null' : "'$value'" }})"
                        class="chip shrink-0 {{ (string) $type === $value && ! $category ? 'chip-on' : '' }}" aria-pressed="{{ (string) $type === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
            <span class="mx-1 w-px shrink-0 bg-zinc-200" aria-hidden="true"></span>
            @foreach ($categories->when($type, fn ($c) => $c->where('type', $type)) as $c)
                <button type="button" wire:key="cat{{ $c->id }}" wire:click="$set('category', {{ $category === $c->id ? 'null' : $c->id }})"
                        class="chip shrink-0 {{ $category === $c->id ? 'chip-on' : '' }}" aria-pressed="{{ $category === $c->id ? 'true' : 'false' }}">{{ $c->name }}</button>
            @endforeach
        </div>
        <div class="grid grid-cols-3 gap-2 lg:flex lg:shrink-0">
            <div>
                <label class="sr-only" for="max_price">{{ __('site.search.max_price') }}</label>
                <select id="max_price" wire:model.live="max_price" class="field py-2">
                    <option value="">{{ __('site.search.any_price') }}</option>
                    @foreach ([500, 1000, 2500, 5000, 10000] as $p)<option value="{{ $p }}">≤ {{ number_format($p) }} {{ __('site.search.per_day') }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="sr-only" for="seats">{{ __('site.search.seats') }}</label>
                <select id="seats" wire:model.live="seats" class="field py-2">
                    <option value="">{{ __('site.search.seats') }}</option>
                    @foreach ([2, 4, 5, 7] as $n)<option value="{{ $n }}">{{ $n }}+</option>@endforeach
                </select>
            </div>
            <div>
                <label class="sr-only" for="sort">{{ __('site.search.sort') }}</label>
                <select id="sort" wire:model.live="sort" class="field py-2">
                    @foreach (\App\Livewire\Search::SORTS as $s)<option value="{{ $s }}">{{ __('site.search.sort_'.$s) }}</option>@endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($cards === null)
        <p class="card text-zinc-600">{{ __('site.search.dates_invalid') }}</p>
    @elseif ($cards->isEmpty())
        <div class="card flex flex-col items-center gap-3 py-12 text-center text-zinc-600">
            <svg viewBox="0 0 24 24" class="h-10 w-10 text-zinc-300" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M8 11h6" stroke-linecap="round"/></svg>
            {{ __('site.search.no_results') }}
        </div>
    @else
        <p class="mb-4 text-sm font-medium text-zinc-500">{{ trans_choice('site.search.results', $cards->total(), ['n' => $cards->total()]) }}</p>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @foreach ($cards as ['vehicle' => $vehicle, 'count' => $count, 'price' => $price])
                @php($m = $vehicle->vehicleModel)
                @php($url = route('vehicles.show', ['id' => $vehicle->id, 'from' => $from, 'to' => $to]))
                <article wire:key="v{{ $vehicle->id }}" class="group relative flex flex-col overflow-hidden rounded-3xl bg-white shadow-[0_1px_2px_rgb(15_23_42/.04),0_12px_32px_-16px_rgb(15_23_42/.18)] ring-1 ring-zinc-200/70 transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-20px_rgb(49_46_129/.35)]">
                    <x-vehicle-image :model="$m">
                        <span class="badge absolute start-3 top-3 bg-white/95 text-zinc-800 shadow-sm backdrop-blur">{{ $m->category->name }}</span>
                        <span class="badge absolute end-3 top-3 bg-emerald-500/95 text-white shadow-sm">
                            <span class="h-1.5 w-1.5 rounded-full bg-white"></span>{{ $withDates ? __('site.vehicle.available') : trans_choice('site.search.available_n', $count, ['n' => $count]) }}
                        </span>
                    </x-vehicle-image>
                    <div class="flex flex-1 flex-col p-5">
                        <div class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ $m->brand->name }}</div>
                        <h2 class="text-lg font-bold tracking-tight text-zinc-900">
                            <a href="{{ $url }}" class="after:absolute after:inset-0">{{ $m->name }}</a>
                        </h2>
                        <ul class="mt-3 grid grid-cols-3 gap-2 text-xs text-zinc-600">
                            <li class="flex flex-col items-center gap-1 rounded-xl bg-zinc-50 px-2 py-2">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="6" cy="6" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="6" cy="18" r="2"/><path d="M6 8v8M18 8v2a2 2 0 0 1-2 2H6"/></svg>
                                <span class="capitalize">{{ $m->transmission ?? '—' }}</span>
                            </li>
                            <li class="flex flex-col items-center gap-1 rounded-xl bg-zinc-50 px-2 py-2">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h12M14 9h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V8l-3-3M7 8h4"/></svg>
                                <span class="capitalize">{{ $m->fuel_type ?? '—' }}</span>
                            </li>
                            <li class="flex flex-col items-center gap-1 rounded-xl bg-zinc-50 px-2 py-2">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>
                                <span>{{ trans_choice('site.vehicle.seats', (int) $m->seats, ['n' => (int) $m->seats]) }}</span>
                            </li>
                        </ul>
                        <div class="mt-3 flex items-center gap-1 text-sm text-zinc-500">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span class="truncate">{{ $vehicle->branch->name }}</span>
                        </div>
                        <div class="mt-auto flex items-end justify-between gap-3 pt-4">
                            @if ($price)
                                <div>
                                    @if ($withDates)
                                        <div class="text-2xl font-extrabold tracking-tight text-zinc-900">{{ \App\Support\Money::format($price['total'], $price['currency']) }}</div>
                                        <div class="text-xs text-zinc-500">{{ __('site.search.total_for_dates') }} · {{ __('site.search.incl_tax') }}</div>
                                    @else
                                        <div><span class="text-2xl font-extrabold tracking-tight text-zinc-900">{{ \App\Support\Money::format($price['per_day'], $price['currency']) }}</span> <span class="text-sm text-zinc-500">{{ __('site.search.per_day') }}</span></div>
                                        <div class="text-xs text-zinc-500">{{ __('site.search.incl_tax') }}</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-sm text-zinc-500">{{ __('site.vehicle.price_unavailable') }}</span>
                            @endif
                            <a href="{{ $url }}" class="btn relative z-10 shrink-0 px-5">{{ __('site.search.book') }}</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-8">{{ $cards->links() }}</div>
    @endif
</div>
