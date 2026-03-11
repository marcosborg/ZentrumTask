<?php

namespace App\Filament\Resources\ChatBotSettings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChatBotSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuracao')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Ativo')
                            ->default(true)
                            ->required(),
                        TextInput::make('name')
                            ->label('Nome do assistente')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('model')
                            ->label('Modelo OpenAI')
                            ->default('gpt-4.1-mini')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('temperature')
                            ->label('Temperatura')
                            ->numeric()
                            ->step('0.01')
                            ->default(0.30)
                            ->minValue(0)
                            ->maxValue(2)
                            ->required(),
                        TextInput::make('max_history_messages')
                            ->label('Mensagens de historico')
                            ->numeric()
                            ->default(20)
                            ->minValue(4)
                            ->maxValue(50)
                            ->required(),
                    ]),
                Section::make('Mensagens')
                    ->components([
                        Textarea::make('welcome_message')
                            ->label('Mensagem inicial')
                            ->rows(3)
                            ->maxLength(4000)
                            ->columnSpanFull(),
                        Textarea::make('system_instructions')
                            ->label('Instrucoes do assistente')
                            ->rows(10)
                            ->maxLength(20000)
                            ->helperText('Estas instrucoes controlam o comportamento do chat para os visitantes.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
