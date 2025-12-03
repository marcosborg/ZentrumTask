<?php

namespace App\Filament\Resources\DriverBillingProfiles\Pages;

use App\Filament\Resources\DriverBillingProfiles\DriverBillingProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDriverBillingProfile extends CreateRecord
{
    protected static string $resource = DriverBillingProfileResource::class;

    protected function getDefaultFormState(): array
    {
        return [
            'driver_id' => request()->integer('driver_id'),
        ];
    }
}
