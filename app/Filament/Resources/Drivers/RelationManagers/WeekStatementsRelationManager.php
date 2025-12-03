<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use App\Filament\Resources\DriverWeekStatements\DriverWeekStatementResource;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WeekStatementsRelationManager extends RelationManager
{
    protected static string $relationship = 'weekStatements';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('week_label')
                    ->label('Semana')
                    ->sortable(),
                TextColumn::make('amount_payable_to_driver')
                    ->label('A pagar')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'draft',
                        'info' => 'confirmed',
                        'success' => 'paid',
                    ])
                    ->label('Estado'),
                TextColumn::make('calculated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->url(fn ($record) => DriverWeekStatementResource::getUrl('view', ['record' => $record])),
                Actions\EditAction::make()
                    ->url(fn ($record) => DriverWeekStatementResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}
