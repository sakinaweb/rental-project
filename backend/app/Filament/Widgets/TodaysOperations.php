<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

// What the counter has to handle today: pickups due, returns due and anything overdue, soonest first.
class TodaysOperations extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = "Today's handovers and returns";

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Booking::query()
                ->with(['user', 'vehicle.vehicleModel.brand', 'vehicle.branch'])
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q) => $q->where('status', BookingStatus::Confirmed->value)->where('start_at', '<=', now()->endOfDay()))
                    ->orWhere(fn (Builder $q) => $q->where('status', BookingStatus::PickedUp->value)->where('end_at', '<=', now()->endOfDay())))
                ->orderByRaw('case when status = ? then start_at else end_at end', [BookingStatus::Confirmed->value]))
            ->poll('60s')
            ->paginated([10, 25])
            ->emptyStateHeading('Nothing due today')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('task')->label('Task')->badge()
                    ->state(fn (Booking $b) => $b->status === BookingStatus::Confirmed ? 'Pickup' : 'Return')
                    ->color(fn (Booking $b) => $b->status === BookingStatus::Confirmed ? 'info' : 'primary'),
                TextColumn::make('due')->label('Due')->dateTime()
                    ->state(fn (Booking $b) => $b->status === BookingStatus::Confirmed ? $b->start_at : $b->end_at)
                    ->color(fn (Booking $b) => ($b->status === BookingStatus::Confirmed ? $b->start_at : $b->end_at)->isPast() ? 'danger' : null)
                    ->description(fn (Booking $b) => ($b->status === BookingStatus::Confirmed ? $b->start_at : $b->end_at)->isPast() ? 'Late' : null),
                TextColumn::make('user.name')->label('Customer')->description(fn (Booking $b) => $b->user?->phone),
                TextColumn::make('vehicle.plate_no')->label('Vehicle')
                    ->description(fn (Booking $b) => $b->vehicle ? $b->vehicle->vehicleModel->brand->name.' '.$b->vehicle->vehicleModel->name : null),
                TextColumn::make('vehicle.branch.name')->label('Branch'),
            ])
            ->recordUrl(fn (Booking $b) => BookingResource::getUrl('view', ['record' => $b]))
            ->recordActions([Action::make('open')->icon('heroicon-m-arrow-top-right-on-square')->url(fn (Booking $b) => BookingResource::getUrl('view', ['record' => $b]))]);
    }
}
