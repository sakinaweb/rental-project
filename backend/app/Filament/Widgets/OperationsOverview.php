<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Services\DashboardStats;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// Dashboard (MD §7.5, decision D5). Booking numbers link to the matching filtered list.
class OperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Operations';

    protected ?string $pollingInterval = '60s';

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $s = app(DashboardStats::class)->summary();
        $revenue = collect($s['revenue_this_month'])->map(fn (int $minor, string $currency) => Money::format($minor, $currency))->join(' · ');
        $list = fn (string $status) => BookingResource::getUrl('index', ['filters' => ['status' => ['value' => $status]]]);

        return [
            Stat::make('Active rentals', $s['active_rentals'])->icon(Heroicon::OutlinedKey)->color('primary')->url($list('picked_up')),
            Stat::make('Pickups today', $s['pickups_today'])->icon(Heroicon::OutlinedArrowUpTray)->color('info')->url($list('confirmed')),
            Stat::make('Returns today', $s['returns_today'])->icon(Heroicon::OutlinedArrowDownTray)->color('info')->url($list('picked_up')),
            Stat::make('Overdue returns', $s['overdue_returns'])->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($s['overdue_returns'] ? 'danger' : 'success')->description('Out past their return time')->url($list('picked_up')),
            Stat::make('Damages to review', $s['damages_to_review'])->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color($s['damages_to_review'] ? 'warning' : 'success')->description('Reported at return, block settlement')->url($list('returned')),
            Stat::make('Awaiting payment', $s['awaiting_payment'])->icon(Heroicon::OutlinedClock)->url($list('pending_payment')),
            Stat::make('Net revenue this month', $revenue ?: '—')->icon(Heroicon::OutlinedBanknotes)->color('success')->description('Completed bookings, excluding tax'),
            Stat::make('Fleet utilisation', $s['utilization_pct'].'%')->icon(Heroicon::OutlinedChartPie)->description('In-service vehicles on rent now'),
            Stat::make('In maintenance', $s['in_maintenance'])->icon(Heroicon::OutlinedCog6Tooth),
            Stat::make('Documents expiring (30 days)', $s['documents_expiring'])->icon(Heroicon::OutlinedDocumentText)
                ->color($s['documents_expiring'] ? 'warning' : 'gray'),
        ];
    }
}
