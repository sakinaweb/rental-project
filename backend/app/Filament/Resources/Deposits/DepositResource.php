<?php

namespace App\Filament\Resources\Deposits;

use App\Filament\Resources\Deposits\Pages\ManageDeposits;
use App\Filament\Support\ReadOnlyResource;
use App\Models\Deposit;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DepositResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Deposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings & money';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        $money = fn (string $name) => TextColumn::make($name)->alignEnd()
            ->formatStateUsing(fn ($state, Deposit $record) => Money::format((int) $state, $record->booking->currency_code));

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('booking'))
            ->columns([
                TextColumn::make('booking_id')->label('Booking'),
                $money('amount'),
                TextColumn::make('status')->badge(),
                $money('refunded_amount'),
                TextColumn::make('refunded_at')->dateTime()->placeholder('—'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeposits::route('/')];
    }
}
