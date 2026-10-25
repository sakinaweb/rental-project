@php($m = fn (int $minor, string $c) => \App\Support\Money::format($minor, $c))
<div class="space-y-6">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('site.investor.title') }}</h1>

    @if ($actionError)<p class="error rounded-xl bg-red-50 p-3">{{ $actionError }}</p>@endif
    @if ($notice)<p class="rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-800">{{ $notice }}</p>@endif

    @if (! $investor || $investor->kyc_status === \App\Models\Investor::KYC_REJECTED)
        <section class="card max-w-xl space-y-4">
            <h2 class="text-lg font-semibold">{{ __('site.investor.apply_title') }}</h2>
            @if ($investor)<p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">{{ __('site.investor.kyc_rejected') }}</p>@endif
            <p class="text-sm text-zinc-600">{{ __('site.investor.apply_intro') }}</p>
            @foreach (['account_holder', 'bank_name', 'account_number', 'routing_code'] as $field)
                <div>
                    <label class="label" for="payout-{{ $field }}">{{ __('site.partner.'.$field) }}</label>
                    <input id="payout-{{ $field }}" wire:model="payout.{{ $field }}" class="field">
                    @error('payout.'.$field)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <button wire:click="apply" wire:loading.attr="disabled" class="btn">{{ __('site.investor.apply_title') }}</button>
        </section>
    @elseif (! $investor->isApproved())
        <p class="card text-zinc-700">{{ __('site.investor.kyc_pending') }}</p>
    @elseif (! $accepted)
        <section class="card max-w-2xl space-y-4">
            <h2 class="text-lg font-semibold">{{ __('site.investor.disclosure_title') }}</h2>
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-relaxed text-amber-950">{{ __('site.investor.disclosure') }}</p>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="accepted" class="mt-0.5 h-4 w-4 rounded border-zinc-300 text-brand-600">
                <span>{{ __('site.investor.accept_label') }}</span>
            </label>
            @error('accepted')<p class="error">{{ $message }}</p>@enderror
            <button wire:click="acknowledge" wire:loading.attr="disabled" class="btn">{{ __('site.investor.accept') }}</button>
        </section>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="space-y-6 lg:col-span-2">
                <div>
                    <h2 class="mb-3 text-lg font-semibold">{{ __('site.investor.plans') }}</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @forelse ($plans as $plan)
                            <div wire:key="plan{{ $plan->id }}" class="card flex flex-col gap-3 border-brand-100">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-brand-600">{{ $plan->category?->name ?? __('site.investor.all_vehicles') }}</div>
                                    <div class="text-lg font-semibold">{{ $plan->name }}</div>
                                </div>
                                <div class="text-3xl font-bold text-zinc-900">{{ rtrim(rtrim(number_format($plan->revenue_share_pct, 2), '0'), '.') }}%</div>
                                <p class="text-sm text-zinc-600">{{ __('site.investor.share', ['pct' => rtrim(rtrim(number_format($plan->revenue_share_pct, 2), '0'), '.')]) }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="badge bg-zinc-100 text-zinc-700">{{ trans_choice('site.investor.term', $plan->term_months, ['n' => $plan->term_months]) }}</span>
                                    <span class="badge bg-zinc-100 text-zinc-700">{{ __('site.investor.minimum', ['amount' => $m($plan->min_amount, $plan->currency_code)]) }}</span>
                                </div>
                                <div class="mt-auto flex items-end gap-2">
                                    <div class="flex-1">
                                        <label class="label" for="amount-{{ $plan->id }}">{{ __('site.investor.amount', ['currency' => $plan->currency_code]) }}</label>
                                        <input id="amount-{{ $plan->id }}" type="number" step="0.01" min="0" wire:model="amounts.{{ $plan->id }}" class="field">
                                    </div>
                                    <button wire:click="invest({{ $plan->id }})" wire:loading.attr="disabled" wire:target="invest({{ $plan->id }})" class="btn">{{ __('site.investor.invest') }}</button>
                                </div>
                                @error('amounts.'.$plan->id)<p class="error">{{ $message }}</p>@enderror
                            </div>
                        @empty
                            <p class="card text-zinc-600 sm:col-span-2">{{ __('site.investor.no_plans') }}</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h2 class="mb-3 text-lg font-semibold">{{ __('site.investor.investments') }}</h2>
                    <div class="space-y-3">
                        @forelse ($investments as $i)
                            <div wire:key="inv{{ $i->id }}" class="card space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">INV-{{ $i->id }} · {{ $i->plan->name }}</span>
                                    <span class="badge {{ $statuses[$i->status] }}">{{ __('site.investor.status_'.$i->status) }}</span>
                                    <span class="ms-auto text-lg font-bold">{{ $m($i->amount, $i->plan->currency_code) }}</span>
                                </div>
                                @if ($i->started_at)
                                    <p class="text-sm text-zinc-600">{{ __('site.investor.period', ['from' => $i->started_at->translatedFormat('j M Y'), 'to' => $i->ends_at->translatedFormat('j M Y')]) }}</p>
                                @endif
                                @if ($i->allocations->isNotEmpty())
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('site.investor.vehicles') }}</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($i->allocations as $a)
                                            <span class="badge bg-brand-50 text-brand-700">{{ $a->vehicle->vehicleModel->brand->name }} {{ $a->vehicle->vehicleModel->name }} · {{ $a->vehicle->plate_no }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="card text-zinc-600">{{ __('site.investor.no_investments') }}</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                @foreach ($balances as $b)
                    <div class="card bg-gradient-to-br from-brand-600 to-brand-900 text-white">
                        <div class="text-xs text-white/70">{{ __('site.partner.balance') }} · {{ $b['currency'] }}</div>
                        <div class="text-2xl font-bold">{{ $m($b['balance'], $b['currency']) }}</div>
                        <div class="text-sm text-white/80">{{ __('site.partner.available') }}: {{ $m($b['available'], $b['currency']) }}</div>
                    </div>
                @endforeach

                <div class="card space-y-3">
                    <h2 class="font-semibold">{{ __('site.partner.request_payout') }}</h2>
                    @if ($balances->isEmpty())
                        <p class="text-sm text-zinc-600">{{ __('site.partner.no_payouts') }}</p>
                    @else
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="label" for="amount">{{ __('site.partner.amount') }}</label>
                                <input id="amount" type="number" step="0.01" min="0" wire:model="amount" class="field">
                            </div>
                            <div>
                                <label class="label" for="currency">{{ __('site.partner.currency') }}</label>
                                <select id="currency" wire:model="currency" class="field">
                                    @foreach ($balances as $b)<option value="{{ $b['currency'] }}">{{ $b['currency'] }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        @error('amount')<p class="error">{{ $message }}</p>@enderror
                        <button wire:click="requestPayout" wire:loading.attr="disabled" class="btn w-full">{{ __('site.partner.request_payout') }}</button>
                    @endif
                </div>

                <div class="card space-y-2">
                    <h2 class="font-semibold">{{ __('site.partner.payouts') }}</h2>
                    @forelse ($payouts as $p)
                        <div wire:key="p{{ $p->id }}" class="flex justify-between gap-2 text-sm">
                            <span>{{ $p->requested_at->translatedFormat('j M Y') }} · {{ __('site.partner.payout_status_'.$p->status) }}</span>
                            <span class="font-medium">{{ $m($p->amount, $p->currency_code) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-600">{{ __('site.partner.no_payouts') }}</p>
                    @endforelse
                </div>
            </aside>
        </div>
    @endif
</div>
