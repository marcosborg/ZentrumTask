<?php

namespace App\Filament\Resources\TaskComments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TaskCommentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('task_id')
                    ->required()
                    ->numeric(),
                TextInput::make('user_id')
                    ->numeric()
                    ->default(null),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_internal')
                    ->required(),
            ]);
    }
}
