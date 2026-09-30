<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;

// Money and history records are written only by the services (payments, wallet, deposits) or by the app (audit log).
trait ReadOnlyResource
{
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
