<?php

namespace App\Filament\Resources\Fleets\Pages;

use App\Filament\Resources\Fleets\FleetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFleet extends EditRecord
{
    protected static string $resource = FleetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
