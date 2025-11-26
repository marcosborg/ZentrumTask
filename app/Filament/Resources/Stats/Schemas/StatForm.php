<?php

namespace App\Filament\Resources\Stats\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('value')
                    ->label('Valor')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
