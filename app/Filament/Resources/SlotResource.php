<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

abstract class SlotResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'TVDE SLOT';

    public static function canAccess(): bool
    {
        return (bool) config('slots.enabled') && parent::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
