<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStats;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// Dashboard (MD §7.5, decision D5).
class OperationsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Operations';

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $s = app(DashboardStats::class)->summary();
        $revenue = collect($s['revenue_this_month'])->map(fn (int $minor, string $currency) => Money::format($minor, $currency))->join(' · ');

        return [
            Stat::make('Active rentals', $s['active_rentals']),
            Stat::make('Pickups today', $s['pickups_today']),
            Stat::make('Returns today', $s['returns_today']),
            Stat::make('Awaiting payment', $s['awaiting_payment']),
            Stat::make('Net revenue this month', $revenue ?: '—')->description('Completed bookings, excluding tax'),
            Stat::make('Fleet utilisation', $s['utilization_pct'].'%')->description('In-service vehicles on rent now'),
            Stat::make('In maintenance', $s['in_maintenance']),
            Stat::make('Documents expiring (30 days)', $s['documents_expiring']),
        ];
    }
}
