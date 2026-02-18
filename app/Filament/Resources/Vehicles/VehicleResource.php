<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 15;

    protected static ?string $recordTitleAttribute = 'license_plate';

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VehicleAllocationsRelationManager::class,
            RelationManagers\VehicleDocumentsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $today = Carbon::today();
        $expiring30 = $today->copy()->addDays(30);

        return parent::getEloquentQuery()
            ->with(['currentAllocation.driver'])
            ->withCount([
                'documents as expired_documents_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', $today),
                'documents as expiring_30_documents_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [$today, $expiring30]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
