<?php

namespace App\Filament\Resources\DriverWeekStatements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriverWeekStatementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver.name')
                    ->label('Motorista')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('week_label')
                    ->label('Semana')
                    ->sortable(),
                TextColumn::make('amount_payable_to_driver')
                    ->label('A pagar ao motorista')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'draft',
                        'info' => 'confirmed',
                        'success' => 'paid',
                    ])
                    ->label('Estado')
                    ->sortable(),
                TextColumn::make('calculated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('driver_id')
                    ->label('Motorista')
                    ->relationship('driver', 'name'),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Rascunho',
                        'confirmed' => 'Confirmado',
                        'paid' => 'Pago',
                    ]),
                Filter::make('week_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('Início')
                            ->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Fim')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('week_start_date', '>=', $date))
                            ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('week_end_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('week_start_date', 'desc');
    }
}
