<?php

namespace App\Filament\Resources\ChatSessions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChatMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('role')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Papel')
                    ->badge()
                    ->colors([
                        'primary' => 'assistant',
                        'warning' => 'user',
                    ]),
                TextColumn::make('content')
                    ->label('Conteudo')
                    ->wrap(),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_tokens')
                    ->label('Tokens')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc');
    }
}
