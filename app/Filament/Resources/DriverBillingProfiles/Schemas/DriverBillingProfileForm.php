<?php

namespace App\Filament\Resources\DriverBillingProfiles\Schemas;

use App\Enums\TaxpayerType;
use App\Enums\VatRefundMode;
use App\Enums\VehicleRentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class DriverBillingProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('driver_id')
                    ->label('Motorista')
                    ->relationship('driver', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->rules(fn (?\App\Models\DriverBillingProfile $record) => [
                        Rule::unique('driver_billing_profiles', 'driver_id')->ignore($record),
                    ])
                    ->disabled(fn (?\App\Models\DriverBillingProfile $record): bool => (bool) $record)
                    ->columnSpan(2),
                Toggle::make('active')
                    ->label('Ativo')
                    ->inline(false)
                    ->default(true),
                DatePicker::make('valid_from')
                    ->label('Válido de')
                    ->native(false),
                DatePicker::make('valid_to')
                    ->label('Válido até')
                    ->native(false),
                Select::make('taxpayer_type')
                    ->label('Tipo de contribuinte')
                    ->options([
                        TaxpayerType::SelfEmployed->value => 'Trabalhador independente',
                        TaxpayerType::EmpresarioEmNomeIndividual->value => 'Empresário em nome individual',
                        TaxpayerType::Sociedade->value => 'Sociedade',
                        TaxpayerType::Dependente->value => 'Dependente',
                        TaxpayerType::Outro->value => 'Outro',
                    ])
                    ->default(TaxpayerType::SelfEmployed->value)
                    ->required(),
                Toggle::make('apply_withholding_tax')
                    ->label('Aplicar retenção na fonte')
                    ->inline(false)
                    ->reactive(),
                TextInput::make('withholding_tax_percent')
                    ->label('Percentual de retenção')
                    ->numeric()
                    ->suffix('%')
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(100)
                    ->visible(fn (callable $get): bool => (bool) $get('apply_withholding_tax')),
                TextInput::make('vat_percent')
                    ->label('IVA %')
                    ->numeric()
                    ->suffix('%')
                    ->step(0.01)
                    ->required()
                    ->default(23),
                Select::make('vat_refund_mode')
                    ->label('IVA')
                    ->options([
                        VatRefundMode::None->value => 'Sem devolução de IVA',
                        VatRefundMode::DriverDeliversVat->value => 'Motorista entrega o IVA',
                    ])
                    ->default(VatRefundMode::None->value),
                TextInput::make('percent_company')
                    ->label('Percentagem empresa')
                    ->numeric()
                    ->suffix('%')
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->default(40),
                TextInput::make('percent_driver')
                    ->label('Percentagem motorista')
                    ->numeric()
                    ->suffix('%')
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->default(60),
                Toggle::make('tips_to_driver')
                    ->label('Gorjetas para o motorista')
                    ->inline(false)
                    ->default(true),
                Select::make('vehicle_rent_type')
                    ->label('Tipo de renda de viatura')
                    ->options([
                        VehicleRentType::None->value => 'Sem renda',
                        VehicleRentType::Weekly->value => 'Semanal',
                        VehicleRentType::Monthly->value => 'Mensal',
                        VehicleRentType::PerKm->value => 'Por km',
                    ])
                    ->default(VehicleRentType::None->value),
                TextInput::make('vehicle_rent_value')
                    ->label('Valor da renda')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€'),
                TextInput::make('additional_fixed_fee')
                    ->label('Taxa administrativa fixa')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€'),
                TextInput::make('additional_percent_fee')
                    ->label('Taxa administrativa %')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('%'),
            ]);
    }
}
