<?php

namespace App\Filament\Resources\Drivers\Tables;

use App\Models\VehicleAllocation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['currentAllocation.vehicle'])
                ->withExists([
                    'billingProfiles as has_active_billing_profile' => fn ($q) => $q->active(),
                ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nif')
                    ->label('NIF')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('iban')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bolt_driver_code')
                    ->label('Codigo Bolt')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('uber_driver_code')
                    ->label('Codigo Uber')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('currentAllocation.vehicle.license_plate')
                    ->label('Viatura atual')
                    ->placeholder('-')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            VehicleAllocation::query()
                                ->select('vehicles.license_plate')
                                ->join('vehicles', 'vehicles.id', '=', 'vehicle_allocations.vehicle_id')
                                ->whereColumn('vehicle_allocations.driver_id', 'drivers.id')
                                ->where('vehicle_allocations.status', 'active')
                                ->whereNull('vehicle_allocations.ends_at')
                                ->orderByDesc('vehicle_allocations.starts_at')
                                ->limit(1),
                            $direction,
                        );
                    }),
                IconColumn::make('has_active_billing_profile')
                    ->label('Perfil ativo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
