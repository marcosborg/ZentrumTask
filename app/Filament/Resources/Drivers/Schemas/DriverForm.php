<?php

namespace App\Filament\Resources\Drivers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telefone')
                    ->maxLength(50),
                TextInput::make('nif')
                    ->label('NIF')
                    ->maxLength(50),
                TextInput::make('iban')
                    ->label('IBAN')
                    ->maxLength(34),
                TextInput::make('license_number')
                    ->label('Carta / Licença')
                    ->maxLength(100),
                Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }
}
