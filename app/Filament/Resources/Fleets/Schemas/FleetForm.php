<?php

namespace App\Filament\Resources\Fleets\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FleetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Produto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome comercial')
                            ->helperText('Ex.: Tesla Model 3 Long Range')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Se ficar vazio, sera gerado automaticamente.')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                        TextInput::make('brand')
                            ->label('Marca')
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label('Modelo')
                            ->maxLength(255),
                        TextInput::make('rental_price')
                            ->label('Preco')
                            ->numeric()
                            ->prefix('EUR')
                            ->inputMode('decimal')
                            ->step('0.01'),
                        TextInput::make('price_suffix')
                            ->label('Sufixo do preco')
                            ->default('/semana')
                            ->maxLength(50),
                        Toggle::make('is_published')
                            ->label('Publicado')
                            ->default(true),
                        Textarea::make('excerpt')
                            ->label('Resumo')
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descricao')
                            ->rows(8)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Imagens')
                    ->columns(1)
                    ->schema([
                        FileUpload::make('photo_path')
                            ->label('Imagem de capa')
                            ->image()
                            ->directory('fleets')
                            ->disk('public')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('4:3')
                            ->imageResizeTargetWidth(1600)
                            ->imageResizeTargetHeight(1200)
                            ->columnSpanFull(),
                        FileUpload::make('gallery_paths')
                            ->label('Galeria')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('fleets/gallery')
                            ->disk('public')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('4:3')
                            ->imageResizeTargetWidth(1600)
                            ->imageResizeTargetHeight(1200)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
