<?php

namespace App\Filament\Resources\PartnerVehicles;

use App\Filament\Resources\PartnerVehicles\Pages\ManagePartnerVehicles;
use App\Filament\Support\ReadOnlyResource;
use App\Models\AuditLog;
use App\Models\PartnerVehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

// Owner decision (Phase 9): a partner's vehicle is listed only after the admin approves it and sets the commission
// (the platform's share of the rental net of tax; default from Settings).
class PartnerVehicleResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = PartnerVehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        $commission = fn () => [TextInput::make('commission_pct')->label('Platform commission (%)')->numeric()->minValue(0)->maxValue(100)->required()];
        $audit = fn (string $action, PartnerVehicle $pv, array $meta) => AuditLog::record("partner_vehicle.$action", $pv, $meta + ['vehicle_id' => $pv->vehicle_id]);

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['partner.user', 'vehicle.vehicleModel.brand', 'vehicle.branch']))
            ->columns([
                TextColumn::make('vehicle.plate_no')->label('Plate')->searchable(),
                TextColumn::make('vehicle.vehicleModel.name')->label('Model')
                    ->formatStateUsing(fn (PartnerVehicle $record) => $record->vehicle->vehicleModel->brand->name.' '.$record->vehicle->vehicleModel->name),
                TextColumn::make('vehicle.branch.name')->label('Branch'),
                TextColumn::make('partner.user.name')->label('Partner')->searchable(),
                TextColumn::make('commission_pct')->label('Commission')->suffix(' %')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label('Added')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($s = [PartnerVehicle::PENDING, PartnerVehicle::ACTIVE, PartnerVehicle::SUSPENDED], $s)),
            ])
            ->recordActions([
                Action::make('approve')->color('success')
                    ->visible(fn (PartnerVehicle $record) => $record->status !== PartnerVehicle::ACTIVE)
                    ->fillForm(fn (PartnerVehicle $record) => ['commission_pct' => $record->commission_pct])
                    ->schema($commission)
                    ->action(function (PartnerVehicle $record, array $data) use ($audit) {
                        $record->update(['status' => PartnerVehicle::ACTIVE, 'commission_pct' => $data['commission_pct']]);
                        $audit('approve', $record, ['commission_pct' => (float) $data['commission_pct']]);
                    }),
                Action::make('commission')->label('Change commission')->color('gray')
                    ->visible(fn (PartnerVehicle $record) => $record->status === PartnerVehicle::ACTIVE)
                    ->fillForm(fn (PartnerVehicle $record) => ['commission_pct' => $record->commission_pct])
                    ->schema($commission)
                    ->action(function (PartnerVehicle $record, array $data) use ($audit) {
                        $before = $record->commission_pct;
                        $record->update(['commission_pct' => $data['commission_pct']]);
                        $audit('commission', $record, ['from' => $before, 'to' => (float) $data['commission_pct']]);
                    }),
                Action::make('suspend')->color('danger')->requiresConfirmation()
                    ->visible(fn (PartnerVehicle $record) => $record->status === PartnerVehicle::ACTIVE)
                    ->action(function (PartnerVehicle $record) use ($audit) {
                        $record->update(['status' => PartnerVehicle::SUSPENDED]);
                        $audit('suspend', $record, []);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePartnerVehicles::route('/')];
    }
}
