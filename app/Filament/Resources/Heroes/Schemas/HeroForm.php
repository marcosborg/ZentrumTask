<?php

namespace App\Filament\Resources\Heroes\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class HeroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label('Titulo')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('subtitle')
                    ->label('Subtitulo')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('cta_text')
                    ->label('Texto do CTA')
                    ->required()
                    ->maxLength(255),
                TextInput::make('cta_link')
                    ->label('Link do CTA')
                    ->url()
                    ->required(),
                TextInput::make('cta_secondary_text')
                    ->label('Texto do CTA 2')
                    ->maxLength(255),
                TextInput::make('cta_secondary_link')
                    ->label('Link do CTA 2')
                    ->url(),
                SpatieMediaLibraryFileUpload::make('hero_image')
                    ->label('Imagem do destaque')
                    ->collection('hero_image')
                    ->conversion('hero_cover')
                    ->disk('public')
                    ->conversionsDisk('public')
                    ->image()
                    ->imageEditor()
                    ->responsiveImages()
                    ->maxFiles(1)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
