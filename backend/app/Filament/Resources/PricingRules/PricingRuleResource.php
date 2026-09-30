<?php

namespace App\Filament\Resources\PricingRules;

use App\Filament\Resources\PricingRules\Pages\ManagePricingRules;
use App\Models\PricingRule;
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
use Illuminate\Validation\Rule;
use UnitEnum;

class PricingRuleResource extends Resource
{
    private const SCOPE_TABLES = ['vehicle' => 'vehicles', 'model' => 'vehicle_models', 'category' => 'vehicle_categories', 'branch' => 'branches'];

    protected static ?string $model = PricingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?string $recordTitleAttribute = 'unit';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('scope_type')->options(array_combine(PricingRule::SCOPES, PricingRule::SCOPES))->required()->live()
                    ->helperText('Most specific wins: vehicle > model > category > branch > global.'),
                TextInput::make('scope_id')->label('Scope id')->integer()
                    ->visible(fn (Get $get) => filled($get('scope_type')) && $get('scope_type') !== 'global')
                    ->required(fn (Get $get) => $get('scope_type') !== 'global')
                    ->dehydratedWhenHidden()->dehydrateStateUsing(fn ($state, Get $get) => $get('scope_type') === 'global' ? null : $state)
                    ->rules([fn (Get $get) => ($table = self::SCOPE_TABLES[$get('scope_type')] ?? null) ? Rule::exists($table, 'id')->whereNull('deleted_at') : 'nullable']),
                Select::make('unit')->options(array_combine(PricingRule::UNITS, PricingRule::UNITS))->required()
                    ->rules([fn (Get $get, ?PricingRule $record) => Rule::unique('pricing_rules', 'unit') // one rule per scope + unit
                        ->where('scope_type', $get('scope_type'))
                        ->where('scope_id', $get('scope_type') === 'global' ? null : (int) $get('scope_id'))
                        ->whereNull('deleted_at')->ignore($record?->id)]),
                TextInput::make('base_rate')->label('Base rate (minor units)')->required()->integer()->minValue(1),
                TextInput::make('weekend_multiplier')->required()->numeric()->minValue(0.01)->maxValue(999.99)->default(1),
                TextInput::make('monthly_discount_pct')->label('Monthly discount %')->required()->numeric()->minValue(0)->maxValue(100)->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit')
            ->columns([
                TextColumn::make('scope_type')
                    ->searchable(),
                TextColumn::make('scope_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit')
                    ->searchable(),
                TextColumn::make('base_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('weekend_multiplier')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_discount_pct')
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
            'index' => ManagePricingRules::route('/'),
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
