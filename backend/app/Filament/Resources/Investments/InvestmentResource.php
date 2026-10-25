<?php

namespace App\Filament\Resources\Investments;

use App\Filament\Resources\Investments\Pages\ManageInvestments;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ReadOnlyResource;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\Vehicle;
use App\Services\InvestorService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

// Investments (Phase 11). Owner decision: the admin activates one when the bank transfer arrives, placing the whole
// amount on company vehicles; closes it when the principal has been paid back; cancels one that is never paid in.
class InvestmentResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Investment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Investors';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        $run = function (Investment $record, string $action, callable $call): void {
            try {
                $call();
            } catch (DomainException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();

                return;
            }
            AuditLog::record("investment.$action", $record, ['amount' => $record->amount]);
            Notification::make()->success()->title('Investment updated')->send();
        };

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['investor.user', 'plan.country', 'allocations.vehicle']))
            ->columns([
                TextColumn::make('id')->label('Ref')->formatStateUsing(fn (int $state) => "INV-$state")->sortable(),
                TextColumn::make('investor.user.name')->label('Investor')->searchable(),
                TextColumn::make('plan.name')->label('Plan'),
                MoneyColumn::make('amount'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => [Investment::ACTIVE => 'success', Investment::PENDING => 'warning', Investment::CANCELLED => 'danger'][$state] ?? 'gray'),
                TextColumn::make('vehicles')->label('Vehicles')->wrap()
                    ->state(fn (Investment $record) => $record->allocations->map(fn ($a) => $a->vehicle->plate_no)->join(', ') ?: '—'),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($s = [Investment::PENDING, Investment::ACTIVE, Investment::CLOSED, Investment::CANCELLED], $s)),
            ])
            ->recordActions([
                Action::make('activate')->label('Transfer received')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (Investment $record) => $record->status === Investment::PENDING)
                    ->modalDescription(fn (Investment $record) => 'Place the whole amount on company vehicles; the term starts now. Amounts in minor units, adding up to '.$record->amount.'.')
                    ->schema(fn (Investment $record) => [
                        Repeater::make('allocations')->required()->minItems(1)->columns(2)->schema([
                            Select::make('vehicle_id')->label('Vehicle')->required()->searchable()->distinct()
                                ->options(Vehicle::where('owner_type', 'company')
                                    ->whereHas('branch.location.city', fn ($q) => $q->where('country_id', $record->plan->country_id))
                                    ->when($record->plan->vehicle_category_id, fn ($q, $c) => $q->whereHas('vehicleModel', fn ($m) => $m->where('category_id', $c)))
                                    ->with('vehicleModel.brand')->get()
                                    ->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->plate_no} · {$v->vehicleModel->brand->name} {$v->vehicleModel->name}"])),
                            TextInput::make('allocated_amount')->label('Amount (minor units)')->integer()->minValue(1)->required(),
                        ])->default([['vehicle_id' => null, 'allocated_amount' => $record->amount]]),
                    ])
                    ->action(fn (Investment $record, array $data) => $run($record, 'activate', fn () => app(InvestorService::class)->activate(
                        $record, collect($data['allocations'])->mapWithKeys(fn ($a) => [(int) $a['vehicle_id'] => (int) $a['allocated_amount']])->all(),
                    ))),
                Action::make('close')->label('Principal repaid')->color('gray')->requiresConfirmation()
                    ->modalDescription('The investment stops earning. Pay the principal back by bank transfer first.')
                    ->visible(fn (Investment $record) => $record->status === Investment::ACTIVE)
                    ->action(fn (Investment $record) => $run($record, 'close', fn () => app(InvestorService::class)->end($record, Investment::CLOSED))),
                Action::make('cancel')->color('danger')->requiresConfirmation()
                    ->visible(fn (Investment $record) => $record->status === Investment::PENDING)
                    ->action(fn (Investment $record) => $run($record, 'cancel', fn () => app(InvestorService::class)->end($record, Investment::CANCELLED))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvestments::route('/')];
    }
}
