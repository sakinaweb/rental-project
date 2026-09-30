<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus as S;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Services\BookingInvoice;
use App\Services\BookingService;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Admin booking actions. Each one calls BookingService, so the §6 guards, row locks, refunds and deposit rules
 * are exactly those the API uses; refusals show the service's message. Every action is audited (D6).
 *
 * @property Booking $record
 */
class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        $bookings = app(BookingService::class);

        return [
            $this->step('pick_up', 'Mark picked up', S::PickedUp, fn (Booking $b) => $bookings->advance($b, S::PickedUp)),
            $this->step('return', 'Mark returned', S::Returned, fn (Booking $b) => $bookings->advance($b, S::Returned)),
            $this->step('complete', 'Mark completed', S::Completed, fn (Booking $b) => $bookings->advance($b, S::Completed)),
            $this->step('no_show', 'Mark no-show', S::NoShow, fn (Booking $b) => $bookings->advance($b, S::NoShow))->color('warning'),
            $this->step('cancel', 'Cancel (refund to wallet)', S::Cancelled, fn (Booking $b) => $bookings->cancel($b))->color('danger'),
            $this->step('settle', 'Settle', S::Settled, fn (Booking $b) => $bookings->settle($b))->color('success'),

            Action::make('assign')->label('Move to another vehicle')
                ->visible(fn () => in_array($this->record->status, [S::Draft, S::PendingPayment, S::Confirmed], true))
                ->schema([
                    Select::make('vehicle_id')->label('Vehicle')->required()->searchable()
                        ->options(fn () => Vehicle::where('status', Vehicle::STATUS_AVAILABLE)->whereKeyNot($this->record->vehicle_id)->pluck('plate_no', 'id')),
                ])
                ->action(fn (array $data) => $this->run('assign', function (Booking $b) use ($bookings, $data) {
                    $from = $b->vehicle_id;
                    $b = $bookings->reassign($b, Vehicle::findOrFail($data['vehicle_id']));

                    return [$b, ['from_vehicle_id' => $from, 'to_vehicle_id' => $b->vehicle_id]];
                })),

            Action::make('invoice')->label('Invoice PDF')->color('gray')->icon('heroicon-o-document-arrow-down')
                ->action(fn () => response()->streamDownload(
                    fn () => print (app(BookingInvoice::class)->pdf($this->record)),
                    app(BookingInvoice::class)->filename($this->record),
                )),
        ];
    }

    /** A status action, shown only when §6 allows the transition from the current status. */
    private function step(string $name, string $label, S $to, Closure $call): Action
    {
        return Action::make($name)->label($label)->requiresConfirmation()
            ->visible(fn () => $this->record->status->canTransitionTo($to))
            ->action(fn () => $this->run($name, fn (Booking $b) => [$call($b), ['from' => $b->status->value, 'to' => $to->value]]));
    }

    /** @param  Closure(Booking): array{0: Booking, 1: array<string, mixed>}  $call */
    private function run(string $name, Closure $call): void
    {
        try {
            [$booking, $meta] = $call($this->record);
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        AuditLog::record("booking.{$name}", $booking, $meta);
        $this->record = $booking->fresh();
        Notification::make()->success()->title('Booking updated')->send();
    }
}
