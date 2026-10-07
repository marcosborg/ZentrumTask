<?php

namespace App\Filament\Resources\RentalParticipations;

use App\Enums\TvdeOperation;
use App\Filament\Resources\DriverParticipations\DriverParticipationResource;
use App\Filament\Resources\RentalParticipations\Pages\ManageRentalParticipations;
use UnitEnum;

class RentalParticipationResource extends DriverParticipationResource
{
    protected static string|UnitEnum|null $navigationGroup = 'TVDE Aluguer';

    protected static ?string $navigationLabel = 'Participações / transições';

    protected static ?string $modelLabel = 'participação de aluguer';

    protected static ?string $pluralModelLabel = 'Participações de aluguer';

    public static function operation(): TvdeOperation
    {
        return TvdeOperation::Rental;
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentalParticipations::route('/')];
    }
}
