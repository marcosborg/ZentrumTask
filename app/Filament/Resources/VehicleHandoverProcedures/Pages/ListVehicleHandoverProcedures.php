<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Pages;

use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicleHandoverProcedures extends ListRecords
{
    protected static string $resource = VehicleHandoverProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
