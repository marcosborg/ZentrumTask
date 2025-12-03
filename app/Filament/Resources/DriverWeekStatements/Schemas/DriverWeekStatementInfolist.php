<?php

namespace App\Filament\Resources\DriverWeekStatements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class DriverWeekStatementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('driver.name')
                    ->label('Motorista')
                    ->columnSpan(2),
                TextEntry::make('week_label')
                    ->label('Semana'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'success',
                        'confirmed' => 'info',
                        default => 'warning',
                    })
                    ->label('Estado'),
                TextEntry::make('gross_total')
                    ->label('Total bruto')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('net_total')
                    ->label('Total líquido')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('tips_total')
                    ->label('Gorjetas')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('company_share')
                    ->label('Quota empresa')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('driver_share')
                    ->label('Quota motorista')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('vat_amount')
                    ->label('IVA')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('withholding_amount')
                    ->label('Retenção')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('expenses_total')
                    ->label('Despesas')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('rent_amount')
                    ->label('Renda viatura')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('additional_fees_total')
                    ->label('Taxas administrativas')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR')),
                TextEntry::make('amount_payable_to_driver')
                    ->label('A pagar ao motorista')
                    ->formatStateUsing(fn ($state) => Number::currency($state, 'EUR'))
                    ->columnSpanFull(),
                TextEntry::make('calculated_at')
                    ->label('Calculado em')
                    ->dateTime(),
            ]);
    }
}
