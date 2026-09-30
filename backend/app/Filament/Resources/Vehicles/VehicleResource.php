<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?string $recordTitleAttribute = 'plate_no';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('model_id')->label('Model')->relationship('vehicleModel', 'name')->searchable()->preload()->required(),
                Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload()->required(),
                TextInput::make('plate_no')->required()->maxLength(20)->unique(ignoreRecord: true), // incl. soft-deleted, like the DB index
                Select::make('status')->options(array_combine(Vehicle::STATUSES, Vehicle::STATUSES))->required()->default(Vehicle::STATUS_AVAILABLE),
                Select::make('owner_type')->options(array_combine(Vehicle::OWNER_TYPES, Vehicle::OWNER_TYPES))->required()->default('company')->live(),
                TextInput::make('owner_id')->label('Partner id')->integer()->exists('partners', 'id')
                    ->visible(fn (Get $get) => $get('owner_type') === 'partner')->requiredIf('owner_type', 'partner'),
                TextInput::make('odometer')->required()->integer()->minValue(0)->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plate_no')
            ->columns([
                TextColumn::make('vehicleModel.name')->label('Model')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->searchable(),
                TextColumn::make('owner_type')
                    ->searchable(),
                TextColumn::make('owner_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('plate_no')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('odometer')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicles::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
