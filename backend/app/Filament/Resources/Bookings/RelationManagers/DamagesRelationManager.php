<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Damage;
use App\Models\Inspection;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Damages feed settlement (MD §6): reported damages block settling; approved ones are deducted from the deposit.
 * Admins record, approve or waive; a damage is never edited or deleted once recorded, and nothing changes after settling.
 */
class DamagesRelationManager extends RelationManager
{
    protected static string $relationship = 'damages';

    private function booking(): Booking
    {
        /** @var Booking */
        return $this->getOwnerRecord();
    }

    public function isReadOnly(): bool
    {
        return $this->booking()->status === BookingStatus::Settled;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('description')->required()->maxLength(2000)->columnSpanFull(),
            TextInput::make('cost')->label('Cost (minor units of the booking currency)')->required()->integer()->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        $decide = fn (string $name, string $status, string $color) => Action::make($name)->color($color)->requiresConfirmation()
            ->visible(fn (Damage $record) => $record->status === Damage::REPORTED && ! $this->isReadOnly())
            ->action(function (Damage $record) use ($name, $status) {
                $record->update(['status' => $status]);
                AuditLog::record("damage.{$name}", $record, ['booking_id' => $record->booking_id, 'cost' => $record->cost]);
            });

        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')->wrap(),
                TextColumn::make('cost')->formatStateUsing(fn ($state) => Money::format((int) $state, $this->booking()->currency_code)),
                TextColumn::make('status')->badge(),
                ImageColumn::make('evidence_json.photos')->label('Photos')->disk(Inspection::disk())->stacked()->limit(4), // from the staff app
                TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()->label('Record damage')
                    ->mutateDataUsing(fn (array $data) => $data + ['status' => Damage::REPORTED])
                    ->after(fn (Damage $record) => AuditLog::record('damage.record', $record, ['booking_id' => $record->booking_id, 'cost' => $record->cost])),
            ])
            ->recordActions([
                $decide('approve', Damage::APPROVED, 'danger'),
                $decide('waive', Damage::WAIVED, 'gray'),
            ]);
    }
}
