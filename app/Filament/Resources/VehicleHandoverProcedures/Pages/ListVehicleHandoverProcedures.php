<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Pages;

use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicleHandoverProcedures extends ListRecords
{
    protected static string $resource = VehicleHandoverProcedureResource::class;

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return parent::table($table)->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('type', '!=', 'inspection'));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
