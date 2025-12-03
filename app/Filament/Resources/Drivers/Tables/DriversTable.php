<?php

namespace App\Filament\Resources\Drivers\Tables;

use App\Filament\Resources\DriverBillingProfiles\DriverBillingProfileResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        $configured = $table
            ->modifyQueryUsing(fn ($query) => $query->withExists([
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
                Action::make('billing_profiles')
                    ->label('Perfis de faturação')
                    ->color('info')
                    ->url(fn ($record) => DriverBillingProfileResource::getUrl('index', [
                        'tableFilters[driver_id][value]' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
                Action::make('new_billing_profile')
                    ->label('Novo perfil')
                    ->color('success')
                    ->url(fn ($record) => DriverBillingProfileResource::getUrl('create', [
                        'driver_id' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');

        return $configured;
    }
}
