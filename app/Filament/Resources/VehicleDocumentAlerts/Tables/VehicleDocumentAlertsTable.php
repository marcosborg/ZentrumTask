<?php

namespace App\Filament\Resources\VehicleDocumentAlerts\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class VehicleDocumentAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('triggered_on', 'desc')
            ->columns([
                TextColumn::make('document.vehicle.license_plate')
                    ->label('Matricula')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('document.title')
                    ->label('Documento')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('level')
                    ->label('Nivel')
                    ->badge()
                    ->colors([
                        'danger' => 'expired',
                        'warning' => ['expiring_7', 'expiring_30'],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'expired' => 'Expirado',
                        'expiring_7' => 'Expira em 7 dias',
                        'expiring_30' => 'Expira em 30 dias',
                        default => $state,
                    }),
                TextColumn::make('triggered_on')
                    ->label('Gerado em')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_resolved')
                    ->label('Resolvido')
                    ->boolean(),
                TextColumn::make('message')
                    ->label('Mensagem')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('unresolved')
                    ->label('Nao resolvidos')
                    ->query(fn ($query) => $query->where('is_resolved', false)),
                SelectFilter::make('level')
                    ->label('Nivel')
                    ->options([
                        'expired' => 'Expirado',
                        'expiring_7' => 'Expira em 7 dias',
                        'expiring_30' => 'Expira em 30 dias',
                    ]),
                SelectFilter::make('is_resolved')
                    ->label('Resolvido')
                    ->options([
                        '0' => 'Nao',
                        '1' => 'Sim',
                    ]),
            ])
            ->recordActions([
                Action::make('markResolved')
                    ->label('Marcar como resolvido')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record): bool => ! $record->is_resolved)
                    ->action(function ($record): void {
                        $record->update([
                            'is_resolved' => true,
                            'resolved_at' => Carbon::now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Alerta resolvido')
                            ->send();
                    }),
                Action::make('reopenAlert')
                    ->label('Reabrir alerta')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record): bool => (bool) $record->is_resolved)
                    ->action(function ($record): void {
                        $record->update([
                            'is_resolved' => false,
                            'resolved_at' => null,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Alerta reaberto')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
