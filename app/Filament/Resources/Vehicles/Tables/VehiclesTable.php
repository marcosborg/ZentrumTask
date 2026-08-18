<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Models\VehicleAllocation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Matricula')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vin')
                    ->label('VIN')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('make')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trim')
                    ->label('Versao')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('color')
                    ->label('Cor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'available',
                        'info' => 'allocated',
                        'warning' => 'maintenance',
                        'danger' => 'accident',
                        'gray' => 'inactive',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Disponivel',
                        'allocated' => 'Alocada',
                        'maintenance' => 'Manutencao',
                        'accident' => 'Acidente',
                        'sold' => 'Vendida',
                        'inactive' => 'Inativa',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tvde' => 'TVDE',
                        'outsource' => 'Outsource',
                        'company' => 'Company',
                        'private' => 'Private',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('weekly_rental_price')
                    ->label('Aluguer semanal')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('currentAllocation.driver.name')
                    ->label('Motorista atual')
                    ->placeholder('-')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            VehicleAllocation::query()
                                ->select('drivers.name')
                                ->join('drivers', 'drivers.id', '=', 'vehicle_allocations.driver_id')
                                ->whereColumn('vehicle_allocations.vehicle_id', 'vehicles.id')
                                ->where('vehicle_allocations.status', 'active')
                                ->whereNull('vehicle_allocations.ends_at')
                                ->orderByDesc('vehicle_allocations.starts_at')
                                ->limit(1),
                            $direction,
                        );
                    }),
                TextColumn::make('expired_documents_count')
                    ->label('Docs expirados')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('expiring_60_documents_count')
                    ->label('Docs a expirar')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'available' => 'Disponivel',
                        'allocated' => 'Alocada',
                        'maintenance' => 'Manutencao',
                        'accident' => 'Acidente',
                        'sold' => 'Vendida',
                        'inactive' => 'Inativa',
                    ]),
                Filter::make('expired_documents')
                    ->label('Com docs expirados')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('documents', function (Builder $query): void {
                            $query->whereNotNull('expires_at')
                                ->where('expires_at', '<', Carbon::today());
                        });
                    }),
                Filter::make('expiring_60_documents')
                    ->label('Com docs a expirar (60d)')
                    ->query(function (Builder $query): Builder {
                        $today = Carbon::today();
                        $limit = $today->copy()->addDays(60);

                        return $query->whereHas('documents', function (Builder $query) use ($today, $limit): void {
                            $query->whereNotNull('expires_at')
                                ->whereBetween('expires_at', [$today, $limit]);
                        });
                    }),
                Filter::make('allocated_now')
                    ->label('Alocadas agora')
                    ->query(fn (Builder $query): Builder => $query->whereHas('allocations', function (Builder $query): void {
                        $query->where('status', 'active')->whereNull('ends_at');
                    })),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
