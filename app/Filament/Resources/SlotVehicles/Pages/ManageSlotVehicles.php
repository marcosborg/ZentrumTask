<?php

namespace App\Filament\Resources\SlotVehicles\Pages;

use App\Filament\Resources\SlotVehicles\SlotVehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlotVehicles extends ManageRecords
{
    protected static string $resource = SlotVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => array_merge($data, ['operation' => 'slot'])),
        ];
    }
}
