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
                    ->label('Título')
                    ->required()
                    ->maxLength(255),
                TextInput::make('cta_text')
                    ->label('Texto do CTA')
                    ->required()
                    ->maxLength(255),
                TextInput::make('cta_link')
                    ->label('Link do CTA')
                    ->url()
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('subtitle')
                    ->label('Subtítulo')
                    ->required()
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('hero_image')
                    ->label('Imagem do destaque')
                    ->collection('hero_image')
                    ->conversion('hero_cover')
                    ->image()
                    ->imageEditor()
                    ->responsiveImages()
                    ->maxFiles(1)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
