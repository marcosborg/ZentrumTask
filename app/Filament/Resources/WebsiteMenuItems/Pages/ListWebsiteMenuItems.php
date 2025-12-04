<?php

namespace App\Filament\Resources\WebsiteMenuItems\Pages;

use App\Filament\Resources\WebsiteMenuItems\WebsiteMenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteMenuItems extends ListRecords
{
    protected static string $resource = WebsiteMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
