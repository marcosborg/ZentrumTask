<?php

namespace App\Filament\Resources\RecipientLists\Pages;

use App\Filament\Resources\RecipientLists\RecipientListResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRecipientList extends EditRecord
{
    protected static string $resource = RecipientListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
