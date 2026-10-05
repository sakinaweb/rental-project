<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.partner.vehicles') }}</h1>
        <a href="{{ route('partner.dashboard') }}" class="ms-auto text-sm hover:underline">{{ __('site.partner.title') }}</a>
    </div>

    @if ($actionError)<p class="error">{{ $actionError }}</p>@endif
    @if ($notice)<p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ $notice }}</p>@endif

    @unless ($partner->isActive())
        <p class="card text-zinc-700">{{ __('site.partner.status_'.$partner->status) }}</p>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="space-y-4 lg:col-span-2">
                @forelse ($vehicles as $pv)
                    @php($v = $pv->vehicle)
                    <div wire:key="v{{ $v->id }}" class="card space-y-3">
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <h2 class="font-semibold">{{ $v->vehicleModel->brand->name }} {{ $v->vehicleModel->name }}</h2>
                            <span class="text-sm text-zinc-500">{{ $v->plate_no }} · {{ $v->branch->name }}</span>
                            <span class="ms-auto rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{{ __('site.partner.vehicle_status_'.$pv->status) }}</span>
                        </div>
                        <p class="text-sm text-zinc-600">{{ __('site.partner.commission') }}: {{ rtrim(rtrim(number_format($pv->commission_pct, 2), '0'), '.') }} %</p>

                        <div class="space-y-1">
                            <h3 class="text-sm font-medium">{{ __('site.partner.blockouts') }}</h3>
                            @forelse ($v->blockouts as $b)
                                <div wire:key="b{{ $b->id }}" class="flex items-center gap-2 text-sm">
                                    <span>{{ $b->start_at->translatedFormat('j M Y, H:i') }} → {{ $b->end_at->translatedFormat('j M Y, H:i') }}@if ($b->reason) · {{ $b->reason }}@endif</span>
                                    <button wire:click="removeBlockout({{ $v->id }}, {{ $b->id }})" class="ms-auto text-red-700 hover:underline">{{ __('site.partner.remove') }}</button>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">—</p>
                            @endforelse
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <div>
                                <label class="label" for="from-{{ $v->id }}">{{ __('site.partner.from') }}</label>
                                <input id="from-{{ $v->id }}" type="datetime-local" wire:model="from.{{ $v->id }}" class="field">
                                @error('from.'.$v->id)<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label" for="to-{{ $v->id }}">{{ __('site.partner.to') }}</label>
                                <input id="to-{{ $v->id }}" type="datetime-local" wire:model="to.{{ $v->id }}" class="field">
                                @error('to.'.$v->id)<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label" for="reason-{{ $v->id }}">{{ __('site.partner.reason') }}</label>
                                <input id="reason-{{ $v->id }}" wire:model="reason.{{ $v->id }}" class="field">
                            </div>
                        </div>
                        <button wire:click="addBlockout({{ $v->id }})" wire:loading.attr="disabled" class="btn">{{ __('site.partner.add_blockout') }}</button>
                    </div>
                @empty
                    <p class="card text-zinc-600">{{ __('site.partner.no_vehicles') }}</p>
                @endforelse
            </section>

            <aside class="card h-fit space-y-3">
                <h2 class="font-semibold">{{ __('site.partner.add_vehicle') }}</h2>
                <div>
                    <label class="label" for="model">{{ __('site.partner.model') }}</label>
                    <select id="model" wire:model="modelId" class="field">
                        <option value="">—</option>
                        @foreach ($models as $model)<option value="{{ $model->id }}">{{ $model->brand->name }} {{ $model->name }}</option>@endforeach
                    </select>
                    @error('modelId')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="branch">{{ __('site.partner.branch') }}</label>
                    <select id="branch" wire:model="branchId" class="field">
                        <option value="">—</option>
                        @foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                    </select>
                    @error('branchId')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="plate">{{ __('site.partner.plate') }}</label>
                    <input id="plate" wire:model="plateNo" class="field">
                    @error('plateNo')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button wire:click="addVehicle" wire:loading.attr="disabled" class="btn w-full">{{ __('site.partner.add_vehicle') }}</button>
                <p class="text-xs text-zinc-500">{{ __('site.partner.approval_note') }}</p>
            </aside>
        </div>
    @endunless
</div>
