<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (string $name, string $label) => TextEntry::make($name)->label($label)
            ->state(fn (Booking $record) => Money::format((int) $record->{$name}, $record->currency_code));

        return $schema->columns(2)->components([
            Section::make('Booking')->columns(2)->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn (BookingStatus $state) => $state->value),
                TextEntry::make('user.email')->label('Customer'),
                TextEntry::make('vehicle.plate_no')->label('Vehicle'),
                TextEntry::make('vehicle.branch.name')->label('Branch'),
                TextEntry::make('start_at')->dateTime(),
                TextEntry::make('end_at')->dateTime(),
                TextEntry::make('completed_at')->dateTime()->placeholder('—'),
                TextEntry::make('coupon.code')->label('Coupon')->placeholder('—'),
            ]),
            Section::make('Money')->columns(2)->schema([
                $money('subtotal', 'Subtotal'),
                $money('discount_total', 'Discounts'),
                $money('tax_total', 'Tax'),
                $money('grand_total', 'Total'),
                $money('deposit_amount', 'Deposit'),
                TextEntry::make('paid')->label('Paid (net of refunds)')
                    ->state(fn (Booking $record) => Money::format($record->amountPaid(), $record->currency_code)),
                TextEntry::make('deposit.status')->label('Deposit status')->placeholder('not held'),
            ]),
        ]);
    }
}
