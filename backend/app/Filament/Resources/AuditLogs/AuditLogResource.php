<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Support\ReadOnlyResource;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('actor.email')->label('Admin')->placeholder('system'),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('subject_type')->formatStateUsing(fn ($state, AuditLog $record) => "{$state} #{$record->subject_id}")->placeholder('—'),
                TextColumn::make('meta_json')->label('Details')->formatStateUsing(fn ($state) => json_encode($state))->wrap(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
