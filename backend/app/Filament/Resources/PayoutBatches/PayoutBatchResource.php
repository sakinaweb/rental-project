<?php

namespace App\Filament\Resources\PayoutBatches;

use App\Filament\Resources\PayoutBatches\Pages\ManagePayoutBatches;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ReadOnlyResource;
use App\Models\AuditLog;
use App\Models\LedgerAccount;
use App\Models\PayoutBatch;
use App\Models\PayoutRequest;
use App\Services\PayoutService;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

// Owner decision (Phase 9): manual payouts. Batch pending requests of a currency, transfer the money by bank, then mark
// the batch paid (posts a 'payout' ledger entry per request). Cancel returns the requests to pending.
class PayoutBatchResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = PayoutBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('processor')->withCount('requests'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                MoneyColumn::make('total_amount'),
                TextColumn::make('requests_count')->label('Requests'),
                TextColumn::make('status')->badge(),
                TextColumn::make('processor.name')->label('By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('processed_at')->dateTime(),
            ])
            ->headerActions([
                Action::make('create')->label('Batch pending requests')
                    ->schema([
                        Select::make('currency_code')->label('Currency')->required()
                            ->options(fn () => LedgerAccount::whereHas('payoutRequests', fn ($q) => $q->where('status', PayoutRequest::PENDING))
                                ->distinct()->orderBy('currency_code')->pluck('currency_code', 'currency_code')),
                    ])
                    ->action(fn (array $data) => self::run('create', fn (PayoutService $p) => $p->createBatch($data['currency_code'], auth()->user()))),
            ])
            ->recordActions([
                Action::make('paid')->label('Mark paid')->color('success')->requiresConfirmation()
                    ->modalDescription('Only after the bank transfers have been made: this takes the amounts out of the partners\' balances.')
                    ->visible(fn (PayoutBatch $record) => $record->status === PayoutBatch::PENDING)
                    ->action(fn (PayoutBatch $record) => self::run('paid', fn (PayoutService $p) => $p->markPaid($record, auth()->user()))),
                Action::make('cancel')->color('danger')->requiresConfirmation()
                    ->visible(fn (PayoutBatch $record) => $record->status === PayoutBatch::PENDING)
                    ->action(fn (PayoutBatch $record) => self::run('cancel', fn (PayoutService $p) => $p->cancel($record, auth()->user()))),
            ]);
    }

    /** @param  Closure(PayoutService): PayoutBatch  $call */
    private static function run(string $name, Closure $call): void
    {
        try {
            $batch = $call(app(PayoutService::class));
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        AuditLog::record("payout_batch.$name", $batch, ['currency_code' => $batch->currency_code, 'total_amount' => $batch->total_amount, 'status' => $batch->status]);
        Notification::make()->success()->title('Payout batch updated')->send();
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayoutBatches::route('/')];
    }
}
