<?php

namespace App\Filament\Resources\WebsiteMenuItems\Pages;

use App\Filament\Resources\WebsiteMenuItems\WebsiteMenuItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWebsiteMenuItem extends EditRecord
{
    protected static string $resource = WebsiteMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
