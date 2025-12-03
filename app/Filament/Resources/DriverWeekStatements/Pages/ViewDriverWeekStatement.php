<?php

namespace App\Filament\Resources\DriverWeekStatements\Pages;

use App\Filament\Resources\DriverWeekStatements\DriverWeekStatementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDriverWeekStatement extends ViewRecord
{
    protected static string $resource = DriverWeekStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
