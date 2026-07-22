<?php

namespace App\Filament\Resources\DriverMessageCampaigns\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriverMessageCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->label('Assunto')->searchable()->limit(60),
                TextColumn::make('deliveries_count')->label('Motoristas')->counts('deliveries')->badge(),
                TextColumn::make('createdBy.name')->label('Criada por')->placeholder('Utilizador removido'),
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
