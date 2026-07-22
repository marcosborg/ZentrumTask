<?php

namespace App\Filament\Resources\DriverMessageCampaigns\Pages;

use App\Filament\Resources\DriverMessageCampaigns\DriverMessageCampaignResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDriverMessageCampaign extends ViewRecord
{
    protected static string $resource = DriverMessageCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
