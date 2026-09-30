<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Earned revenue (decision D3): bookings completed or settled, by completion date, one row per period and currency.
 * net_revenue = grand_total − tax_total (tax is collected for the authorities). Deposits are not revenue.
 *
 * @phpstan-type RevenueRow array{period: string, currency_code: string, bookings: int<0, max>, subtotal: int, discount_total: int, tax_total: int, grand_total: int, net_revenue: int}
 */
class RevenueReport
{
    public const COLUMNS = ['period', 'currency_code', 'bookings', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_revenue'];

    /** @return Collection<int, RevenueRow> */
    public function rows(CarbonInterface $from, CarbonInterface $to, string $groupBy = 'day', ?int $countryId = null, ?int $branchId = null): Collection
    {
        $format = $groupBy === 'month' ? 'Y-m' : 'Y-m-d';

        // ponytail: grouped in PHP to stay database-portable (MD §1); move to SQL if report volumes get large.
        return Booking::query()
            ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Settled->value])
            ->whereBetween('completed_at', [$from, $to])
            ->when($branchId, fn ($q, $id) => $q->whereHas('vehicle', fn ($v) => $v->where('branch_id', $id)))
            ->when($countryId, fn ($q, $id) => $q->whereHas('vehicle.branch.location.city', fn ($c) => $c->where('country_id', $id)))
            ->get(['completed_at', 'currency_code', 'subtotal', 'discount_total', 'tax_total', 'grand_total'])
            ->groupBy(fn (Booking $b) => $b->completed_at->format($format).'|'.$b->currency_code)
            ->map(function (Collection $group, string $key): array {
                [$period, $currency] = explode('|', $key);

                return [
                    'period' => $period,
                    'currency_code' => $currency,
                    'bookings' => $group->count(),
                    'subtotal' => (int) $group->sum('subtotal'),
                    'discount_total' => (int) $group->sum('discount_total'),
                    'tax_total' => (int) $group->sum('tax_total'),
                    'grand_total' => (int) $group->sum('grand_total'),
                    'net_revenue' => (int) ($group->sum('grand_total') - $group->sum('tax_total')),
                ];
            })
            ->sortKeys()
            ->values();
    }
}
