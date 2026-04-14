<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleWebsitePhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'websitePhotos';

    protected static ?string $title = 'Fotos Site';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                FileUpload::make('photo_path')
                    ->label('Foto')
                    ->image()
                    ->directory('vehicle-website-photos')
                    ->disk('public')
                    ->imageEditor()
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('4:3')
                    ->imageResizeTargetWidth(1600)
                    ->imageResizeTargetHeight(1200)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('alt_text')
                    ->label('Texto alternativo')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt_text')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->height(64),
                TextColumn::make('alt_text')
                    ->label('Texto alternativo')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label('Ordem')
                    ->sortable(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
