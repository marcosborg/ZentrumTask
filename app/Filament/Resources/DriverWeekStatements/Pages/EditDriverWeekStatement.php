<?php

namespace App\Filament\Resources\DriverWeekStatements\Pages;

use App\Filament\Resources\DriverWeekStatements\DriverWeekStatementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDriverWeekStatement extends EditRecord
{
    protected static string $resource = DriverWeekStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
