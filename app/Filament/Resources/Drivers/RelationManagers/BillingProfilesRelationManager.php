<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use App\Enums\TaxpayerType;
use App\Enums\VatRefundMode;
use App\Enums\VehicleRentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillingProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'billingProfiles';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Toggle::make('active')
                    ->label('Ativo')
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
                    ->required()
                    ->columnSpan(2),
                Toggle::make('apply_withholding_tax')
                    ->label('Aplicar retenção')
                    ->inline(false)
                    ->reactive(),
                TextInput::make('withholding_tax_percent')
                    ->label('Retenção %')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('%')
                    ->visible(fn (callable $get): bool => (bool) $get('apply_withholding_tax')),
                TextInput::make('vat_percent')
                    ->label('IVA %')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('%')
                    ->default(23)
                    ->required(),
                Select::make('vat_refund_mode')
                    ->label('IVA')
                    ->options([
                        VatRefundMode::None->value => 'Sem devolução',
                        VatRefundMode::DriverDeliversVat->value => 'Motorista entrega',
                    ])
                    ->default(VatRefundMode::None->value),
                TextInput::make('percent_company')
                    ->label('% Empresa')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('%')
                    ->default(40)
                    ->required(),
                TextInput::make('percent_driver')
                    ->label('% Motorista')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('%')
                    ->default(60)
                    ->required(),
                Toggle::make('tips_to_driver')
                    ->label('Gorjetas para motorista')
                    ->inline(false)
                    ->default(true),
                Select::make('vehicle_rent_type')
                    ->label('Renda viatura')
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
                    ->label('Taxa fixa')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€'),
                TextInput::make('additional_percent_fee')
                    ->label('Taxa %')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('%'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('valid_from')
                    ->label('Válido de')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->label('Válido até')
                    ->date()
                    ->sortable(),
                TextColumn::make('percent_company')
                    ->label('% Empresa')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%'),
                TextColumn::make('percent_driver')
                    ->label('% Motorista')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%'),
                TextColumn::make('vat_refund_mode')
                    ->label('IVA')
                    ->badge(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
