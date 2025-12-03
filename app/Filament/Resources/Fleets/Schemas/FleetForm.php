<?php

namespace App\Filament\Resources\Fleets\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FleetForm
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
                FileUpload::make('photo_path')
                    ->label('Foto (quadrada)')
                    ->image()
                    ->directory('fleets')
                    ->disk('public')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('1:1')
                    ->imageResizeTargetWidth(600)
                    ->imageResizeTargetHeight(600)
                    ->columnSpanFull(),
            ]);
    }
}
