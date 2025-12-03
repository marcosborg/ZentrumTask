<?php

namespace App\Filament\Resources\DriverBillingProfiles\Pages;

use App\Filament\Resources\DriverBillingProfiles\DriverBillingProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDriverBillingProfiles extends ListRecords
{
    protected static string $resource = DriverBillingProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
