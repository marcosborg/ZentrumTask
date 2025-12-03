<?php

namespace App\Filament\Resources\DriverBillingProfiles\Pages;

use App\Filament\Resources\DriverBillingProfiles\DriverBillingProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDriverBillingProfile extends EditRecord
{
    protected static string $resource = DriverBillingProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
