<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Pages;

use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListVehicleInspections extends ListRecords
{
    protected static string $resource = VehicleHandoverProcedureResource::class;

    protected static ?string $title = 'Inspeções';

    public function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'inspection'));
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova inspeção')
                ->url(VehicleHandoverProcedureResource::getUrl('create', ['type' => 'inspection'])),
        ];
    }
}
