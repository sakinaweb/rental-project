<?php

namespace App\Filament\Resources\Partners;

use App\Filament\Resources\Partners\Pages\ManagePartners;
use App\Filament\Support\ReadOnlyResource;
use App\Models\AuditLog;
use App\Models\Partner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

// Partner applications (Phase 9): approve, reject or suspend. Suspending hides all of the partner's vehicles.
class PartnerResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Partner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        $set = fn (string $name, string $label, array $from, string $to, string $color) => Action::make($name)->label($label)->color($color)
            ->requiresConfirmation()
            ->visible(fn (Partner $record) => in_array($record->status, $from, true))
            ->action(function (Partner $record) use ($name, $to) {
                $before = $record->status;
                $record->update(['status' => $to]);
                AuditLog::record("partner.$name", $record, ['from' => $before, 'to' => $to]);
            });

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user')->withCount('partnerVehicles'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('partner_vehicles_count')->label('Vehicles'),
                TextColumn::make('payout_details_json')->label('Payout details')->wrap()
                    ->formatStateUsing(fn (Partner $record) => implode(' · ', array_filter($record->payout_details_json ?? []))),
                TextColumn::make('created_at')->label('Applied')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($s = [Partner::PENDING, Partner::ACTIVE, Partner::SUSPENDED, Partner::REJECTED], $s)),
            ])
            ->recordActions([
                $set('approve', 'Approve', [Partner::PENDING, Partner::SUSPENDED], Partner::ACTIVE, 'success'),
                $set('reject', 'Reject', [Partner::PENDING], Partner::REJECTED, 'danger'),
                $set('suspend', 'Suspend', [Partner::ACTIVE], Partner::SUSPENDED, 'danger'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePartners::route('/')];
    }
}
