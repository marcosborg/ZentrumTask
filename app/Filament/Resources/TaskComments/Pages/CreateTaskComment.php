<?php

namespace App\Filament\Resources\TaskComments\Pages;

use App\Filament\Resources\TaskComments\TaskCommentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaskComment extends CreateRecord
{
    protected static string $resource = TaskCommentResource::class;
}
