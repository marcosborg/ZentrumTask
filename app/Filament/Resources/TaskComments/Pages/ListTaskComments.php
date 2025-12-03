<?php

namespace App\Filament\Resources\TaskComments\Pages;

use App\Filament\Resources\TaskComments\TaskCommentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaskComments extends ListRecords
{
    protected static string $resource = TaskCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
