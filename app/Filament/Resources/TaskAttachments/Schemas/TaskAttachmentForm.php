<?php

namespace App\Filament\Resources\TaskAttachments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaskAttachmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('task_id')
                    ->required()
                    ->numeric(),
                TextInput::make('disk')
                    ->required()
                    ->default('public'),
                TextInput::make('path')
                    ->required(),
                TextInput::make('original_name')
                    ->default(null),
                TextInput::make('mime_type')
                    ->default(null),
                TextInput::make('size')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
