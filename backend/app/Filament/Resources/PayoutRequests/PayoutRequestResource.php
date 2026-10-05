<?php

namespace App\Filament\Resources\PayoutRequests;

use App\Filament\Resources\PayoutRequests\Pages\ManagePayoutRequests;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ReadOnlyResource;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\PayoutRequest;
use App\Services\PayoutService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

// Partners' payout requests. Pending ones go into a batch (Payout batches) or are rejected here.
class PayoutRequestResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = PayoutRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('account'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('account.owner_id')->label('Partner')
                    ->formatStateUsing(fn (PayoutRequest $record) => Partner::with('user')->find($record->account->owner_id)?->user?->name ?? "#{$record->account->owner_id}"),
                MoneyColumn::make('amount'),
                TextColumn::make('status')->badge(),
                TextColumn::make('payout_batch_id')->label('Batch'),
                TextColumn::make('requested_at')->dateTime()->sortable(),
                TextColumn::make('processed_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($s = [PayoutRequest::PENDING, PayoutRequest::BATCHED, PayoutRequest::PAID, PayoutRequest::REJECTED], $s)),
            ])
            ->recordActions([
                Action::make('reject')->color('danger')->requiresConfirmation()
                    ->visible(fn (PayoutRequest $record) => $record->status === PayoutRequest::PENDING)
                    ->action(function (PayoutRequest $record) {
                        try {
                            app(PayoutService::class)->reject($record);
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();

                            return;
                        }
                        AuditLog::record('payout_request.reject', $record, ['amount' => $record->amount]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayoutRequests::route('/')];
    }
}
