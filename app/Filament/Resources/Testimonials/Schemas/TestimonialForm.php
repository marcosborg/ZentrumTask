<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('author_name')
                    ->label('Autor')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('content')
                    ->label('Conteudo')
                    ->rows(4)
                    ->required()
                    ->columnSpanFull(),
                Select::make('stars')
                    ->label('Estrelas')
                    ->options([
                        1 => '1',
                        2 => '2',
                        3 => '3',
                        4 => '4',
                        5 => '5',
                    ])
                    ->required()
                    ->default(5),
                FileUpload::make('photo_path')
                    ->label('Foto (quadrada)')
                    ->image()
                    ->directory('testimonials')
                    ->disk('public')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('1:1')
                    ->imageResizeTargetWidth(600)
                    ->imageResizeTargetHeight(600)
                    ->columnSpanFull(),
            ]);
    }
}
