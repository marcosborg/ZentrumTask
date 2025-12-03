<?php

namespace App\Filament\Resources\DriverWeekStatements\Schemas;

use App\Models\DriverBillingProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DriverWeekStatementInputForm
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
                    ->live(),
                Select::make('billing_profile_id')
                    ->label('Perfil de faturação')
                    ->options(fn (callable $get) => DriverBillingProfile::query()
                        ->where('driver_id', $get('driver_id'))
                        ->pluck('id', 'id')
                        ->all())
                    ->disabled(fn (callable $get): bool => (bool) ! $get('driver_id'))
                    ->getOptionLabelFromRecordUsing(fn (DriverBillingProfile $profile): string => '#'.$profile->id.' • '.($profile->valid_from?->format('d/m/Y') ?? 'sem início'))
                    ->searchable()
                    ->required()
                    ->helperText('Prefere-se o perfil ativo do motorista para o cálculo.'),
                TextInput::make('tvde_week_id')
                    ->label('Semana (ID plataforma)')
                    ->numeric()
                    ->columnSpan(1),
                DatePicker::make('week_start_date')
                    ->label('Início da semana')
                    ->native(false)
                    ->required(),
                DatePicker::make('week_end_date')
                    ->label('Fim da semana')
                    ->native(false)
                    ->required(),
                TextInput::make('gross_total')
                    ->label('Total bruto (G)')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€')
                    ->required(),
                TextInput::make('net_total')
                    ->label('Total líquido (N)')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€')
                    ->required(),
                TextInput::make('tips_total')
                    ->label('Gorjetas (T)')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€')
                    ->default(0),
                TextInput::make('expenses_total')
                    ->label('Despesas imputáveis')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€')
                    ->default(0),
                TextInput::make('rent_amount')
                    ->label('Renda viatura imputada')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€')
                    ->default(0),
                TextInput::make('additional_fees_total')
                    ->label('Taxas administrativas (se já calculadas)')
                    ->numeric()
                    ->step(0.01)
                    ->prefix('€')
                    ->helperText('Se deixar vazio, será calculado a partir do perfil.'),
            ]);
    }
}
