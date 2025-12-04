<?php

namespace App\Filament\Resources\WebsiteMenuItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WebsiteMenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
