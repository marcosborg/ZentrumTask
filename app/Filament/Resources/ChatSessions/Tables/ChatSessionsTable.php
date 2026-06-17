<?php

namespace App\Filament\Resources\ChatSessions\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Enums\IconPosition;
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
                TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'app' => 'App',
                        default => 'Chat',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'app' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'whatsapp' => 'heroicon-m-device-phone-mobile',
                        'app' => 'heroicon-m-squares-2x2',
                        default => 'heroicon-m-chat-bubble-left-right',
                    })
                    ->iconPosition(IconPosition::Before),
                TextColumn::make('messages_count')
                    ->label('Mensagens')
                    ->counts('messages')
                    ->sortable(),
                TextColumn::make('external_contact')
                    ->label('Contacto')
                    ->state(fn ($record): string => (string) (
                        data_get($record->meta, 'external_name')
                        ?: data_get($record->meta, 'external_id')
                    ))
                    ->searchable(query: fn ($query, string $search) => $query->where('meta', 'like', '%'.$search.'%'))
                    ->toggleable(),
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
