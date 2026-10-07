<?php

namespace App\Filament\Resources\SlotVehicles\Pages;

use App\Filament\Resources\SlotVehicles\SlotVehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSlotVehicle extends EditRecord
{
    protected static string $resource = SlotVehicleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['operation'] = 'slot';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
