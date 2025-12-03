<?php

namespace App\Filament\Resources\TaskAttachments\Pages;

use App\Filament\Resources\TaskAttachments\TaskAttachmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaskAttachment extends EditRecord
{
    protected static string $resource = TaskAttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
