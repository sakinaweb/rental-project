<?php

namespace App\Filament\Resources\LedgerEntries\Pages;

use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLedgerEntries extends ManageRecords
{
    protected static string $resource = LedgerEntryResource::class;
}
