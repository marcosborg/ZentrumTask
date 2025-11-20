<?php

namespace App\Filament\Resources\RecipientLists\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class RecipientListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('contacts')
                    ->label('Contacts')
                    ->multiple()
                    ->relationship('contacts', 'email') // usa a relação do modelo
                    ->preload()
                    ->searchable()
                    ->helperText('Selecione os contactos que farão parte desta lista.'),
            ]);
    }
}
