<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class VehicleHandoverProcedureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo')
                    ->columns(3)
                    ->components([
                        TextEntry::make('type')
                            ->label('Tipo')
                            ->formatStateUsing(fn (string $state): string => $state === 'delivery' ? 'Entrega' : 'Devolucao')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'delivery' ? 'success' : 'warning'),
                        TextEntry::make('performed_at')
                            ->label('Executado em')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('operator.name')
                            ->label('Operador'),
                        TextEntry::make('vehicle.license_plate')
                            ->label('Viatura'),
                        TextEntry::make('driver.name')
                            ->label('Motorista'),
                        TextEntry::make('allocation_effective_start_date')
                            ->label('Inicio efetivo')
                            ->date('d/m/Y'),
                        TextEntry::make('allocation_effective_end_date')
                            ->label('Fim efetivo')
                            ->date('d/m/Y'),
                        TextEntry::make('battery_minimum_percent')
                            ->label('Bateria acordada')
                            ->formatStateUsing(fn ($state): string => $state ? "{$state}%" : '-'),
                        TextEntry::make('deposit_paid_amount')
                            ->label('Caucao')
                            ->formatStateUsing(fn ($state): string => $state ? Number::currency((float) $state, 'EUR') : '-'),
                    ]),
                Section::make('Documento')
                    ->components([
                        Html::make(fn ($record): HtmlString => new HtmlString($record->html_snapshot ?: '<p>Documento ainda nao gerado.</p>')),
                    ]),
            ]);
    }
}
