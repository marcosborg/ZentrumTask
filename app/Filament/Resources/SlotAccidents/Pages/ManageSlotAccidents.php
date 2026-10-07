<?php

namespace App\Filament\Resources\SlotAccidents\Pages;

use App\Filament\Resources\SlotAccidents\SlotAccidentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlotAccidents extends ManageRecords
{
    protected static string $resource = SlotAccidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
