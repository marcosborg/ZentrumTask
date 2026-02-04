<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Conteudo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titulo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Se ficar vazio, sera gerado automaticamente.')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('excerpt')
                            ->label('Resumo')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        RichEditor::make('body')
                            ->label('Texto')
                            ->required()
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('featured_image')
                            ->label('Imagem de destaque')
                            ->collection('featured_image')
                            ->conversion('featured_cover')
                            ->disk('public')
                            ->conversionsDisk('public')
                            ->image()
                            ->imageEditor()
                            ->responsiveImages()
                            ->maxFiles(1)
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label('Publicado')
                            ->default(false),
                        DateTimePicker::make('published_at')
                            ->label('Publicado em')
                            ->seconds(false),
                    ]),
                Section::make('SEO')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
