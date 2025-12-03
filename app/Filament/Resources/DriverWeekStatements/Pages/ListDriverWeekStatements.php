<?php

namespace App\Filament\Resources\DriverWeekStatements\Pages;

use App\Filament\Resources\DriverWeekStatements\DriverWeekStatementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDriverWeekStatements extends ListRecords
{
    protected static string $resource = DriverWeekStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
