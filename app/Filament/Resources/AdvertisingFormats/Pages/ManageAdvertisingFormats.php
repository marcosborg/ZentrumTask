<?php

namespace App\Filament\Resources\AdvertisingFormats\Pages;

use App\Filament\Resources\AdvertisingFormats\AdvertisingFormatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAdvertisingFormats extends ManageRecords
{
    protected static string $resource = AdvertisingFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
