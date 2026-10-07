<?php

namespace App\Filament\Resources\VehicleCheckups\Pages;

use App\Filament\Resources\VehicleCheckups\VehicleCheckupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleCheckups extends ManageRecords
{
    protected static string $resource = VehicleCheckupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
