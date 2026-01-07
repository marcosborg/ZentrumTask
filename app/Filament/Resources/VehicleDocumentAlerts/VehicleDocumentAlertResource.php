<?php

namespace App\Filament\Resources\VehicleDocumentAlerts;

use App\Filament\Resources\VehicleDocumentAlerts\Pages\ListVehicleDocumentAlerts;
use App\Filament\Resources\VehicleDocumentAlerts\Tables\VehicleDocumentAlertsTable;
use App\Models\VehicleDocumentAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VehicleDocumentAlertResource extends Resource
{
    protected static ?string $model = VehicleDocumentAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'message';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function table(Table $table): Table
    {
        return VehicleDocumentAlertsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['document.vehicle']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleDocumentAlerts::route('/'),
        ];
    }
}
