<?php

namespace App\Filament\Resources\DriverMessageCampaigns\Pages;

use App\Filament\Resources\DriverMessageCampaigns\DriverMessageCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDriverMessageCampaign extends EditRecord
{
    protected static string $resource = DriverMessageCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
