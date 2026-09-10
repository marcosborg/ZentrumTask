<?php

namespace App\Filament\Resources\VanReservations;

use App\Filament\Resources\VanReservations\Pages\EditVanReservation;
use App\Filament\Resources\VanReservations\Pages\ListVanReservations;
use App\Filament\Resources\VanReservations\Schemas\VanReservationForm;
use App\Filament\Resources\VanReservations\Tables\VanReservationsTable;
use App\Models\VanReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VanReservationResource extends Resource
{
    protected static ?string $model = VanReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static \UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?string $modelLabel = 'Reserva de carrinha';

    protected static ?string $pluralModelLabel = 'Reservas de carrinhas';

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static ?string $navigationLabel = 'Reservas de carrinhas';

    protected static ?int $navigationSort = 51;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return VanReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VanReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVanReservations::route('/'),
            'edit' => EditVanReservation::route('/{record}/edit'),
        ];
    }
}
