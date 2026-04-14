<?php

namespace App\Filament\Resources\Fleets\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FleetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->height(50),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('rental_price')
                    ->label('Preco')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('price_suffix')
                    ->label('Periodo')
                    ->toggleable(),
                IconColumn::make('is_published')
                    ->label('Publicado')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc');
    }
}
