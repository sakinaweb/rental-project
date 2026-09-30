<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Filament\Resources\WalletTransactions\Pages\ManageWalletTransactions;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ReadOnlyResource;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class WalletTransactionResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings & money';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user_id')->label('User')->searchable(),
                TextColumn::make('type')->badge(),
                MoneyColumn::make('amount'),
                MoneyColumn::make('balance_after'),
                TextColumn::make('reference_type')->formatStateUsing(fn ($state, WalletTransaction $record) => "{$state} #{$record->reference_id}"),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(array_combine($t = [WalletTransaction::TOPUP, WalletTransaction::PAYMENT, WalletTransaction::REFUND, WalletTransaction::DEPOSIT_RELEASE], $t)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWalletTransactions::route('/')];
    }
}
