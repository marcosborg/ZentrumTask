<?php

namespace App\Filament\Resources\DriverWeekStatements\Schemas;

use App\Enums\StatementStatus;
use App\Models\DriverWeekStatement;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class DriverWeekStatementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Placeholder::make('driver')
                    ->label('Motorista')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->driver?->name),
                Placeholder::make('billing_profile')
                    ->label('Perfil de faturação')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->billingProfile?->id ? 'Perfil #'.$record->billing_profile_id : null),
                Placeholder::make('week_label')
                    ->label('Semana')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->week_label),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        StatementStatus::Draft->value => 'Rascunho',
                        StatementStatus::Confirmed->value => 'Confirmado',
                        StatementStatus::Paid->value => 'Pago',
                    ])
                    ->required(),
                Placeholder::make('gross_total')
                    ->label('Total bruto')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->gross_total !== null ? Number::currency($record->gross_total, 'EUR') : null),
                Placeholder::make('net_total')
                    ->label('Total líquido')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->net_total !== null ? Number::currency($record->net_total, 'EUR') : null),
                Placeholder::make('tips_total')
                    ->label('Gorjetas')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->tips_total !== null ? Number::currency($record->tips_total, 'EUR') : null),
                Placeholder::make('company_share')
                    ->label('Quota empresa')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->company_share !== null ? Number::currency($record->company_share, 'EUR') : null),
                Placeholder::make('driver_share')
                    ->label('Quota motorista')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->driver_share !== null ? Number::currency($record->driver_share, 'EUR') : null),
                Placeholder::make('vat_amount')
                    ->label('IVA')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->vat_amount !== null ? Number::currency($record->vat_amount, 'EUR') : null),
                Placeholder::make('withholding_amount')
                    ->label('Retenção na fonte')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->withholding_amount !== null ? Number::currency($record->withholding_amount, 'EUR') : null),
                Placeholder::make('expenses_total')
                    ->label('Despesas')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->expenses_total !== null ? Number::currency($record->expenses_total, 'EUR') : null),
                Placeholder::make('rent_amount')
                    ->label('Renda viatura')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->rent_amount !== null ? Number::currency($record->rent_amount, 'EUR') : null),
                Placeholder::make('additional_fees_total')
                    ->label('Taxas administrativas')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->additional_fees_total !== null ? Number::currency($record->additional_fees_total, 'EUR') : null),
                Placeholder::make('amount_payable_to_driver')
                    ->label('A pagar ao motorista')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->amount_payable_to_driver !== null ? Number::currency($record->amount_payable_to_driver, 'EUR') : null)
                    ->columnSpanFull(),
                Placeholder::make('calculated_at')
                    ->label('Calculado em')
                    ->content(fn (?DriverWeekStatement $record): ?string => $record?->calculated_at?->format('d/m/Y H:i')),
            ]);
    }
}
