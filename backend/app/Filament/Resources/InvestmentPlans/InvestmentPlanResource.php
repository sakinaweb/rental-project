<?php

namespace App\Filament\Resources\InvestmentPlans;

use App\Filament\Resources\InvestmentPlans\Pages\ManageInvestmentPlans;
use App\Filament\Support\MoneyColumn;
use App\Models\InvestmentPlan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

// Investor plans (Phase 11). Only active plans take new investments; closing a plan leaves running ones untouched.
// Kept (not deleted) once used, because investments point at them.
class InvestmentPlanResource extends Resource
{
    protected static ?string $model = InvestmentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Investors';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('country_id')->relationship('country', 'name')->required()->preload()
                ->helperText('The plan\'s currency is this country\'s, and only its company vehicles can be funded.'),
            Select::make('vehicle_category_id')->relationship('category', 'name')->preload()->placeholder('Any category'),
            TextInput::make('min_amount')->label('Minimum (minor units)')->integer()->minValue(1)->required()
                ->helperText('Like every amount: ₹10,000.00 is 1000000.'),
            TextInput::make('term_months')->integer()->minValue(1)->maxValue(120)->required(),
            TextInput::make('revenue_share_pct')->label('Investors\' share (%)')->numeric()->minValue(0.01)->maxValue(100)->required()
                ->helperText('Of each settled rental of the funded vehicles, net of tax; split between a vehicle\'s investors by amount.'),
            Select::make('status')->options(array_combine($s = [InvestmentPlan::DRAFT, InvestmentPlan::ACTIVE, InvestmentPlan::CLOSED], $s))
                ->default(InvestmentPlan::DRAFT)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['country', 'category']))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('country.name'),
                TextColumn::make('category.name')->placeholder('Any'),
                MoneyColumn::make('min_amount')->label('Minimum'),
                TextColumn::make('term_months')->label('Term (months)'),
                TextColumn::make('revenue_share_pct')->label('Share')->suffix('%'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => [InvestmentPlan::ACTIVE => 'success', InvestmentPlan::CLOSED => 'gray'][$state] ?? 'warning'),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($s = [InvestmentPlan::DRAFT, InvestmentPlan::ACTIVE, InvestmentPlan::CLOSED], $s)),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvestmentPlans::route('/')];
    }
}
