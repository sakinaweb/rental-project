<?php

namespace App\Filament\Resources\VehicleCategories\Pages;

use App\Filament\Resources\VehicleCategories\VehicleCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleCategories extends ManageRecords
{
    protected static string $resource = VehicleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
