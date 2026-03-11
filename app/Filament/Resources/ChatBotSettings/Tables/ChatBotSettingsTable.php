<?php

namespace App\Filament\Resources\ChatBotSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChatBotSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Assistente')
                    ->searchable(),
                IconColumn::make('is_enabled')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('model')
                    ->label('Modelo'),
                TextColumn::make('max_history_messages')
                    ->label('Historico'),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
