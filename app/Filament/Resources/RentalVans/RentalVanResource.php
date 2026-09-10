<?php

namespace App\Filament\Resources\RentalVans;

use App\Filament\Resources\RentalVans\Pages\CreateRentalVan;
use App\Filament\Resources\RentalVans\Pages\EditRentalVan;
use App\Filament\Resources\RentalVans\Pages\ListRentalVans;
use App\Filament\Resources\RentalVans\Schemas\RentalVanForm;
use App\Filament\Resources\RentalVans\Tables\RentalVansTable;
use App\Models\RentalVan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RentalVanResource extends Resource
{
    protected static ?string $model = RentalVan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static \UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?string $modelLabel = 'Carrinha de aluguer';

    protected static ?string $pluralModelLabel = 'Carrinhas de aluguer';

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static ?string $navigationLabel = 'Carrinhas de aluguer';

    protected static ?int $navigationSort = 50;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RentalVanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentalVansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentalVans::route('/'),
            'create' => CreateRentalVan::route('/create'),
            'edit' => EditRentalVan::route('/{record}/edit'),
        ];
    }
}
