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
                    ->sortable()
                    ->formatStateUsing(function (string $state, $record): string {
                        $isChild = (bool) $record->parent_id;
                        $prefix = $isChild ? '<span class="text-secondary">&rarr;</span>' : '';
                        $indentClass = $isChild ? 'ms-3' : '';

                        return <<<HTML
                            <span class="d-inline-flex align-items-center gap-2 {$indentClass}">
                                {$prefix}
                                <span>{$state}</span>
                            </span>
                        HTML;
                    })
                    ->html(),
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
