<?php

namespace App\Filament\Resources\InvestmentPlans\Pages;

use App\Filament\Resources\InvestmentPlans\InvestmentPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInvestmentPlans extends ManageRecords
{
    protected static string $resource = InvestmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
