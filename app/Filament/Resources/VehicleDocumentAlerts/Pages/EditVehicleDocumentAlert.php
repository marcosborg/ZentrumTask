<?php

namespace App\Filament\Resources\VehicleDocumentAlerts\Pages;

use App\Filament\Resources\VehicleDocumentAlerts\VehicleDocumentAlertResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleDocumentAlert extends EditRecord
{
    protected static string $resource = VehicleDocumentAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
