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
            Action::make('togglePhotos')
                ->label(request()->boolean('photos') ? 'Ocultar fotos' : 'Mostrar fotos')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->url(fn (): string => request()->boolean('photos')
                    ? request()->url()
                    : request()->fullUrlWithQuery(['photos' => 1])),
            DeleteAction::make(),
        ];
    }
}
