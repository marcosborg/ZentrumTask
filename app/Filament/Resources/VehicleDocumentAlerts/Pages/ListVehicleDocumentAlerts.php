<?php

namespace App\Filament\Resources\VehicleDocumentAlerts\Pages;

use App\Filament\Resources\VehicleDocumentAlerts\VehicleDocumentAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicleDocumentAlerts extends ListRecords
{
    protected static string $resource = VehicleDocumentAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
