<?php

namespace App\Filament\Resources\RecipientLists\Pages;

use App\Filament\Resources\RecipientLists\RecipientListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecipientLists extends ListRecords
{
    protected static string $resource = RecipientListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
