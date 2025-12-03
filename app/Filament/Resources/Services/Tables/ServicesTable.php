<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('icon')
                    ->label('Icone')
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('fa-solid ', '', $state) : '-')
                    ->badge(),
                TextColumn::make('icon_color')
                    ->label('Cor do icone')
                    ->formatStateUsing(fn (?string $state) => $state ?: 'n/d')
                    ->badge()
                    ->extraAttributes(fn (?string $state) => $state ? ['style' => "background-color: {$state}; color: #0c1b3d;"] : []),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc');
    }
}
