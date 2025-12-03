<?php

namespace App\Filament\Resources\Fleets;

use App\Filament\Resources\Fleets\Pages\CreateFleet;
use App\Filament\Resources\Fleets\Pages\EditFleet;
use App\Filament\Resources\Fleets\Pages\ListFleets;
use App\Filament\Resources\Fleets\Schemas\FleetForm;
use App\Filament\Resources\Fleets\Tables\FleetsTable;
use App\Models\Fleet;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FleetResource extends Resource
{
    protected static ?string $model = Fleet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    public static function form(Schema $schema): Schema
    {
        return FleetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FleetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFleets::route('/'),
            'create' => CreateFleet::route('/create'),
            'edit' => EditFleet::route('/{record}/edit'),
        ];
    }
}
