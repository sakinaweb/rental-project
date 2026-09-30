<?php

namespace App\Filament\Resources\VehicleModels\Pages;

use App\Filament\Resources\VehicleModels\VehicleModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleModels extends ManageRecords
{
    protected static string $resource = VehicleModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
