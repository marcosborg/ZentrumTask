<?php

namespace App\Filament\Resources\DriverMessageCampaigns\Pages;

use App\Filament\Resources\DriverMessageCampaigns\DriverMessageCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDriverMessageCampaigns extends ListRecords
{
    protected static string $resource = DriverMessageCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
