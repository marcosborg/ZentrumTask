<?php

namespace App\Filament\Resources\DriverWeekStatements\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('type')
                    ->label('Tipo')
                    ->required()
                    ->maxLength(50),
                TextInput::make('amount')
                    ->label('Valor')
                    ->numeric()
                    ->step(0.01)
                    ->required()
                    ->prefix('€'),
                Textarea::make('description')
                    ->label('Descrição')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('meta')
                    ->label('Meta (JSON opcional)')
                    ->columnSpanFull()
                    ->afterStateHydrated(function (Textarea $component, $state): void {
                        $component->state($state ? json_encode($state, JSON_PRETTY_PRINT) : null);
                    })
                    ->dehydrateStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return null;
                        }

                        $decoded = json_decode($state, true);

                        return is_array($decoded) ? $decoded : null;
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->wrap(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('EUR'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
