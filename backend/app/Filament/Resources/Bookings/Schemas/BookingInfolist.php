<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Inspection;
use App\Support\Money;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
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
            // Phase 10: the staff app's handover and return checklists.
            Section::make('Handover and return')->columnSpanFull()->schema([
                RepeatableEntry::make('inspections')->hiddenLabel()->placeholder('No checklist yet.')->columns(4)->schema([
                    TextEntry::make('stage')->badge(),
                    TextEntry::make('odometer')->numeric(),
                    TextEntry::make('fuel_level')->suffix(' %'),
                    TextEntry::make('inspector.name')->label('By')->placeholder('—'),
                    TextEntry::make('created_at')->label('At')->dateTime(),
                    TextEntry::make('notes')->placeholder('—')->columnSpan(3),
                    ImageEntry::make('photos_json')->label('Photos')->disk(Inspection::disk())->height(96)->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
