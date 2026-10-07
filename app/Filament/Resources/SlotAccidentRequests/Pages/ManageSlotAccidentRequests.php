<?php

namespace App\Filament\Resources\SlotAccidentRequests\Pages;

use App\Filament\Resources\SlotAccidentRequests\SlotAccidentRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlotAccidentRequests extends ManageRecords
{
    protected static string $resource = SlotAccidentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
