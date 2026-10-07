<?php

namespace App\Filament\Resources\SlotPacks\Pages;

use App\Filament\Resources\SlotPacks\SlotPackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlotPacks extends ManageRecords
{
    protected static string $resource = SlotPackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
