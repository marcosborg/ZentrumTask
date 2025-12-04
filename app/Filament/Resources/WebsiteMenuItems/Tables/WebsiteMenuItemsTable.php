<?php

namespace App\Filament\Resources\WebsiteMenuItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebsiteMenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.label')
                    ->label('Pai')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('url')
                    ->label('URL')
                    ->limit(60)
                    ->tooltip(fn (string $state): string => $state)
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('position');
    }
}
