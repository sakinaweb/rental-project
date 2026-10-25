<?php

namespace App\Filament\Resources\LedgerEntries;

use App\Filament\Resources\LedgerEntries\Pages\ManageLedgerEntries;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ReadOnlyResource;
use App\Models\LedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

// Earnings ledger (MD §3), read-only: entries come only from settlements and payouts.
class LedgerEntryResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = LedgerEntry::class;

    protected static ?string $navigationLabel = 'Earnings ledger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('account'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('account.owner_type')->label('Account')
                    ->formatStateUsing(fn (LedgerEntry $record) => $record->account->ownerLabel()),
                TextColumn::make('entry_type')->badge(),
                MoneyColumn::make('amount'),
                MoneyColumn::make('running_balance'),
                TextColumn::make('booking_id')->label('Booking'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('entry_type')->options(array_combine($t = [LedgerEntry::CREDIT_SHARE, LedgerEntry::PAYOUT], $t)),
                SelectFilter::make('owner')->label('Account')->options(['platform' => 'Platform', 'partner' => 'Partners', 'investor' => 'Investors'])
                    ->query(fn ($query, array $data) => $query->when($data['value'], fn ($q, $type) => $q->whereHas('account', fn ($a) => $a->where('owner_type', $type)))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLedgerEntries::route('/')];
    }
}
