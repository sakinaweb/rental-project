<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

// Admin dashboard numbers (decision D5). Plain queries so the widget stays a thin view.
class DashboardStats
{
    public function __construct(private RevenueReport $revenue) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $today = [now()->startOfDay(), now()->endOfDay()];
        $fleet = Vehicle::where('status', Vehicle::STATUS_AVAILABLE)->count();
        $onRent = Booking::where('status', BookingStatus::PickedUp->value)->distinct()->count('vehicle_id');

        return [
            'active_rentals' => Booking::where('status', BookingStatus::PickedUp->value)->count(),
            'pickups_today' => Booking::where('status', BookingStatus::Confirmed->value)->whereBetween('start_at', $today)->count(),
            'returns_today' => Booking::where('status', BookingStatus::PickedUp->value)->whereBetween('end_at', $today)->count(),
            'awaiting_payment' => Booking::where('status', BookingStatus::PendingPayment->value)->count(),
            'revenue_this_month' => $this->revenue->rows(now()->startOfMonth(), now(), 'month')
                ->mapWithKeys(fn (array $row) => [$row['currency_code'] => $row['net_revenue']])->all(),
            'utilization_pct' => $fleet ? (int) round($onRent / $fleet * 100) : 0,
            'in_maintenance' => Vehicle::where('status', 'maintenance')->count(),
            'documents_expiring' => DB::table('vehicle_documents')->whereNull('deleted_at')
                ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
        ];
    }
}
