<?php

namespace App\Filament\Resources\VehicleHandoverProcedures;

use App\Filament\Resources\VehicleHandoverProcedures\Pages\CreateVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\EditVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\ListVehicleHandoverProcedures;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\ViewVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\Schemas\VehicleHandoverProcedureForm;
use App\Filament\Resources\VehicleHandoverProcedures\Schemas\VehicleHandoverProcedureInfolist;
use App\Filament\Resources\VehicleHandoverProcedures\Tables\VehicleHandoverProceduresTable;
use App\Models\VehicleHandoverProcedure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VehicleHandoverProcedureResource extends Resource
{
    protected static ?string $model = VehicleHandoverProcedure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 35;

    protected static ?string $navigationLabel = 'Entregas & Devolucoes';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return VehicleHandoverProcedureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleHandoverProceduresTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VehicleHandoverProcedureInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleHandoverProcedures::route('/'),
            'create' => CreateVehicleHandoverProcedure::route('/create'),
            'view' => ViewVehicleHandoverProcedure::route('/{record}'),
            'edit' => EditVehicleHandoverProcedure::route('/{record}/edit'),
        ];
    }
}
