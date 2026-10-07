<?php

namespace App\Support;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class SlotForms
{
    public static function fiscal(): array
    {
        return [
            Select::make('taxpayer_type')->label('Enquadramento')->options(['self_employed' => 'Independente', 'empresario_em_nome_individual' => 'ENI', 'sociedade' => 'Sociedade', 'dependente' => 'Dependente', 'outro' => 'Outro'])->default('self_employed')->required(),
            Select::make('vat_refund_mode')->label('IVA entregue pelo motorista')->options(['none' => 'Não', 'driver_delivers_vat' => 'Sim'])->default('none')->required(),
            TextInput::make('vat_percent')->label('IVA %')->numeric()->minValue(0)->maxValue(100)->default(0),
            Checkbox::make('apply_withholding_tax')->label('Aplicar retenção'),
            TextInput::make('withholding_tax_percent')->label('Retenção %')->numeric()->minValue(0)->maxValue(100)->default(0),
            DatePicker::make('valid_from')->label('Válido desde')->required(),
            DatePicker::make('valid_to')->label('Válido até')->afterOrEqual('valid_from'),
        ];
    }
}
