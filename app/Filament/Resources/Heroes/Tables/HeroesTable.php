<?php

namespace App\Filament\Resources\Heroes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HeroesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('hero_image')
                    ->collection('hero_image')
                    ->conversion('hero_thumb')
                    ->label('Imagem'),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('cta_text')
                    ->label('CTA'),
                TextColumn::make('cta_link')
                    ->label('Link')
                    ->limit(50),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
