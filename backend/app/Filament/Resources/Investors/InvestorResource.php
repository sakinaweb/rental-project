<?php

namespace App\Filament\Resources\Investors;

use App\Filament\Resources\Investors\Pages\ManageInvestors;
use App\Filament\Support\ReadOnlyResource;
use App\Models\AuditLog;
use App\Models\Investor;
use App\Services\InvestorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

// Investor applications (Phase 11): check identity outside the platform, then approve or reject KYC here.
class InvestorResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Investor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Investors';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        $kyc = fn (string $name, string $label, string $to, string $color) => Action::make($name)->label($label)->color($color)
            ->requiresConfirmation()
            ->visible(fn (Investor $record) => $record->kyc_status !== $to)
            ->action(function (Investor $record) use ($name, $to) {
                $before = $record->kyc_status;
                $record->update(['kyc_status' => $to]);
                AuditLog::record("investor.kyc_$name", $record, ['from' => $before, 'to' => $to]);
            });

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user.country', 'disclosures'])->withCount('investments'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('user.country.name')->label('Country'),
                TextColumn::make('kyc_status')->label('KYC')->badge()
                    ->color(fn (string $state) => [Investor::KYC_APPROVED => 'success', Investor::KYC_REJECTED => 'danger'][$state] ?? 'warning'),
                IconColumn::make('disclosure')->label('Risk disclosure')->boolean()
                    ->state(fn (Investor $record) => $record->disclosures->contains('version', InvestorService::DISCLOSURE_VERSION)),
                TextColumn::make('investments_count')->label('Investments'),
                TextColumn::make('payout_details_json')->label('Payout details')->wrap()
                    ->formatStateUsing(fn (Investor $record) => implode(' · ', array_filter($record->payout_details_json ?? []))),
                TextColumn::make('created_at')->label('Applied')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('kyc_status')->label('KYC')->options(array_combine($s = [Investor::KYC_PENDING, Investor::KYC_APPROVED, Investor::KYC_REJECTED], $s)),
            ])
            ->recordActions([
                $kyc('approve', 'Approve KYC', Investor::KYC_APPROVED, 'success'),
                $kyc('reject', 'Reject KYC', Investor::KYC_REJECTED, 'danger'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvestors::route('/')];
    }
}
