<?php

namespace App\Filament\Resources\VanReservations\Pages;

use App\Filament\Resources\VanReservations\VanReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVanReservations extends ListRecords
{
    protected static string $resource = VanReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
