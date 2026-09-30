<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (BookingStatus $state) => $state->value),
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('vehicle.plate_no')->label('Vehicle')->searchable(),
                TextColumn::make('start_at')->dateTime()->sortable(),
                TextColumn::make('end_at')->dateTime()->sortable(),
                MoneyColumn::make('grand_total')->label('Total'),
                MoneyColumn::make('deposit_amount')->label('Deposit')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(BookingStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
