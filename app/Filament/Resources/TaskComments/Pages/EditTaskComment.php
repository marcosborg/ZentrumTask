<?php

namespace App\Filament\Resources\TaskComments\Pages;

use App\Filament\Resources\TaskComments\TaskCommentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaskComment extends EditRecord
{
    protected static string $resource = TaskCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
