<?php

namespace App\Filament\Resources\RentalBalances;

class RentalBalanceResource extends \App\Filament\Resources\SlotBalances\SlotBalanceResource
{
    protected static string|\UnitEnum|null $navigationGroup = 'TVDE Aluguer';

    public static function operation(): \App\Enums\TvdeOperation
    {
        return \App\Enums\TvdeOperation::Rental;
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRentalBalances::route('/')];
    }
}
