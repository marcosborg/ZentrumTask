<?php

namespace App\Filament\Resources\ChatSessions\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChatSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sessao')
                    ->columns(2)
                    ->components([
                        TextInput::make('session_token')
                            ->label('Token da sessao')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        TextInput::make('ip_address')
                            ->label('IP')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('started_at')
                            ->label('Inicio')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('last_message_at')
                            ->label('Ultima mensagem')
                            ->disabled()
                            ->dehydrated(false),
                        KeyValue::make('meta')
                            ->label('Meta')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
