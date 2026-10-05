@php($m = fn (int $minor, string $c) => \App\Support\Money::format($minor, $c))
<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('site.partner.title') }}</h1>
        @if ($partner?->isActive())
            <a href="{{ route('partner.vehicles') }}" class="btn ms-auto">{{ __('site.partner.vehicles') }}</a>
        @endif
    </div>

    @if ($actionError)<p class="error">{{ $actionError }}</p>@endif
    @if ($notice)<p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ $notice }}</p>@endif

    @if (! $partner || $partner->status === \App\Models\Partner::REJECTED)
        <section class="card max-w-xl space-y-3">
            <h2 class="font-semibold">{{ __('site.partner.apply_title') }}</h2>
            @if ($partner)<p class="text-sm text-amber-800">{{ __('site.partner.status_rejected') }}</p>@endif
            <p class="text-sm text-zinc-600">{{ __('site.partner.apply_intro') }}</p>
            @foreach (['account_holder', 'bank_name', 'account_number', 'routing_code'] as $field)
                <div>
                    <label class="label" for="payout-{{ $field }}">{{ __('site.partner.'.$field) }}</label>
                    <input id="payout-{{ $field }}" wire:model="payout.{{ $field }}" class="field">
                    @error('payout.'.$field)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <button wire:click="apply" wire:loading.attr="disabled" class="btn">{{ __('site.partner.apply') }}</button>
        </section>
    @elseif (! $partner->isActive())
        <p class="card text-zinc-700">{{ __('site.partner.status_'.$partner->status) }}</p>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="space-y-6 lg:col-span-2">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @forelse ($balances as $b)
                        <div class="card">
                            <div class="text-xs text-zinc-500">{{ __('site.partner.balance') }} · {{ $b['currency'] }}</div>
                            <div class="text-xl font-bold">{{ $m($b['balance'], $b['currency']) }}</div>
                            <div class="text-sm text-zinc-600">{{ __('site.partner.available') }}: {{ $m($b['available'], $b['currency']) }}</div>
                        </div>
                    @empty
                        <div class="card sm:col-span-2 text-zinc-600">{{ __('site.partner.no_balance') }}</div>
                    @endforelse
                </div>

                <div class="card overflow-x-auto p-0">
                    <h2 class="p-4 font-semibold">{{ __('site.partner.earnings') }}</h2>
                    @if ($entries->isEmpty())
                        <p class="px-4 pb-4 text-sm text-zinc-600">{{ __('site.partner.no_earnings') }}</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-zinc-500"><tr>
                                <th class="p-3 text-start">{{ __('site.partner.date') }}</th><th class="p-3 text-start">{{ __('site.partner.type') }}</th>
                                <th class="p-3 text-start">{{ __('site.partner.booking') }}</th><th class="p-3 text-end">{{ __('site.partner.amount') }}</th>
                                <th class="p-3 text-end">{{ __('site.partner.balance') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($entries as $e)
                                    <tr wire:key="e{{ $e->id }}" class="border-t border-zinc-100">
                                        <td class="p-3">{{ $e->created_at->translatedFormat('j M Y, H:i') }}</td>
                                        <td class="p-3">{{ __('site.partner.entry_'.$e->entry_type) }}</td>
                                        <td class="p-3">{{ $e->booking_id ? '#'.$e->booking_id : '—' }}</td>
                                        <td @class(['p-3 text-end font-medium', 'text-emerald-700' => $e->amount > 0])>{{ $m($e->amount, $e->currency_code) }}</td>
                                        <td class="p-3 text-end">{{ $m($e->running_balance, $e->currency_code) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div>{{ $entries->links() }}</div>
            </section>

            <aside class="space-y-6">
                <div class="card space-y-3">
                    <h2 class="font-semibold">{{ __('site.partner.request_payout') }}</h2>
                    @if ($balances->isEmpty())
                        <p class="text-sm text-zinc-600">{{ __('site.partner.no_balance') }}</p>
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
