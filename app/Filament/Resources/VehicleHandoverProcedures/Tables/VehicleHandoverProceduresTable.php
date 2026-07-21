<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehicleHandoverProceduresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('performed_at', 'desc')
            ->columns([
                TextColumn::make('performed_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'delivery' ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state === 'delivery' ? 'Entrega' : 'Devolucao'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'draft' ? 'warning' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'draft' ? 'Rascunho' : 'Concluido'),
                TextColumn::make('draft_step')
                    ->label('Fase')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'photos' => 'Fotografias', 'videos' => 'Videos', 'checklist' => 'Checklist',
                        'damage' => 'Danos e fotos', 'notes' => 'Notas', 'signatures' => 'Assinaturas',
                        'review' => 'Conclusao', default => '-',
                    }),
                TextColumn::make('vehicle.license_plate')
                    ->label('Viatura')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('driver.name')
                    ->label('Motorista')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('operator.name')
                    ->label('Operador')
                    ->toggleable(),
                TextColumn::make('allocation_effective_start_date')
                    ->label('Inicio efetivo')
                    ->date('d/m/Y')
                    ->toggleable(),
                TextColumn::make('allocation_effective_end_date')
                    ->label('Fim efetivo')
                    ->date('d/m/Y')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'delivery' => 'Entrega',
                        'return' => 'Devolucao',
                    ]),
                SelectFilter::make('status')->label('Estado')->options(['draft' => 'Rascunho', 'completed' => 'Concluido']),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ]);
    }
}
