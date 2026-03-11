<?php

namespace App\Filament\Resources\ChatSessions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChatSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_token')
                    ->label('Token')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('messages_count')
                    ->label('Mensagens')
                    ->counts('messages')
                    ->sortable(),
                TextColumn::make('last_user_message')
                    ->label('Ultima mensagem')
                    ->state(fn ($record): string => (string) optional(
                        $record->messages()->where('role', 'user')->latest('id')->first()
                    )->content)
                    ->limit(70),
                TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('last_message_at')
                    ->label('Ultima atividade')
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
