<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Pages;

use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVehicleHandoverProcedure extends CreateRecord
{
    protected static string $resource = VehicleHandoverProcedureResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var VehicleHandoverProcedureService $service */
        $service = app(VehicleHandoverProcedureService::class);

        /** @var VehicleHandoverProcedure $procedure */
        $procedure = $service->create($data, auth()->user());

        return $procedure;
    }
}
