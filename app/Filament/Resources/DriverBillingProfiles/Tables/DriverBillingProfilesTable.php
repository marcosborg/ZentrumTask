<?php

namespace App\Filament\Resources\DriverBillingProfiles\Tables;

use App\Enums\VatRefundMode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriverBillingProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver.name')
                    ->label('Motorista')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('valid_from')
                    ->label('Válido de')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_to')
                    ->label('Válido até')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('percent_company')
                    ->label('% Empresa')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('percent_driver')
                    ->label('% Motorista')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('vat_refund_mode')
                    ->label('IVA')
                    ->formatStateUsing(
                        fn (VatRefundMode|string|null $state): ?string => match (true) {
                            $state === VatRefundMode::DriverDeliversVat,
                            $state === VatRefundMode::DriverDeliversVat->value => 'Motorista entrega',
                            $state === VatRefundMode::None,
                            $state === VatRefundMode::None->value => 'Sem devolução de IVA',
                            default => null,
                        },
                    )
                    ->sortable(),
                TextColumn::make('vehicle_rent_type')
                    ->label('Renda viatura')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('additional_fixed_fee')
                    ->label('Taxa fixa')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('additional_percent_fee')
                    ->label('Taxa %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('driver_id')
                    ->label('Motorista')
                    ->relationship('driver', 'name'),
                SelectFilter::make('active')
                    ->label('Estado')
                    ->options([
                        '1' => 'Ativos',
                        '0' => 'Inativos',
                    ]),
                SelectFilter::make('vat_refund_mode')
                    ->label('IVA')
                    ->options([
                        'none' => 'Sem devolução de IVA',
                        'driver_delivers_vat' => 'Motorista entrega',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('valid_from', 'desc');
    }
}
