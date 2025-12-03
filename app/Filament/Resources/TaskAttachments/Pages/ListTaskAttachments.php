<?php

namespace App\Filament\Resources\TaskAttachments\Pages;

use App\Filament\Resources\TaskAttachments\TaskAttachmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaskAttachments extends ListRecords
{
    protected static string $resource = TaskAttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
