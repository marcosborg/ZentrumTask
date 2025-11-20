<?php

namespace App\Filament\Resources\Tasks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('board_id')
                    ->required()
                    ->relationship('board', 'name'),
                Select::make('stage_id')
                    ->required()
                    ->relationship('stage', 'name'),
                Select::make('assigned_to_id')
                    ->relationship('assignedTo', 'name'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('priority')
                    ->required()
                    ->options([
                        'normal' => 'Normal',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->default('normal'),
                DateTimePicker::make('due_at'),
                TextInput::make('external_reference')
                    ->default(null),
                Textarea::make('meta')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
