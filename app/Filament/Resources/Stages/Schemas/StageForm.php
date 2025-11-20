<?php

namespace App\Filament\Resources\Stages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Schema;

class StageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('board_id')
                    ->label('Board')
                    ->relationship('board', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                ColorPicker::make('color')
                    ->default(null),
                Toggle::make('is_initial')
                    ->required(),
                Toggle::make('is_final')
                    ->required(),
                Toggle::make('freeze_sla')
                    ->required(),
            ]);
    }
}
