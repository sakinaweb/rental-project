<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ReadOnlyResource;
use App\Models\Payment;
use App\Services\PaymentService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings & money';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('booking_id')->label('Booking')->placeholder('wallet top-up'),
                TextColumn::make('user_id')->label('User'),
                TextColumn::make('gateway')->badge(),
                TextColumn::make('gateway_ref')->searchable()->toggleable(),
                MoneyColumn::make('amount'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($s = [Payment::PENDING, Payment::SUCCEEDED, Payment::FAILED, Payment::EXPIRED], $s)),
                SelectFilter::make('gateway')->options(array_combine($g = [...PaymentService::GATEWAYS, Payment::WALLET], $g)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayments::route('/')];
    }
}
