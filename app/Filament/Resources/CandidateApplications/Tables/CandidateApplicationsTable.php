<?php

namespace App\Filament\Resources\CandidateApplications\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CandidateApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Telemovel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('iban')
                    ->searchable()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'submitted',
                        'warning' => 'incomplete',
                        'info' => 'converted',
                        'gray' => 'draft',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'submitted' => 'Submetida',
                        'incomplete' => 'Incompleta',
                        'converted' => 'Convertida',
                        default => 'Rascunho',
                    }),
                TextColumn::make('current_step')
                    ->label('Passo atual')
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Submetida em')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'submitted' => 'Submetida',
                        'incomplete' => 'Incompleta',
                        'draft' => 'Rascunho',
                        'converted' => 'Convertida',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
