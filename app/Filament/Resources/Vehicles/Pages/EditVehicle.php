<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('initialPhotos')
                ->label('Fotografias iniciais')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->url(VehicleResource::getUrl('initial-photos', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
