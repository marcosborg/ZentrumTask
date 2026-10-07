<?php

namespace App\Filament\Resources\SlotAccidentRequests;

use App\Filament\Resources\SlotAccidentRequests\Pages\ManageSlotAccidentRequests;
use App\Filament\Resources\SlotResource;
use App\Models\SlotAccidentRequest;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlotAccidentRequestResource extends SlotResource
{
    protected static ?string $model = SlotAccidentRequest::class;

    protected static ?string $navigationLabel = 'Pedidos de indemnização / substituição';

    protected static ?string $modelLabel = 'Pedidos de indemnização / substituição';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('slot_accident_id')->label('Processo')->relationship('accident', 'reference')->getOptionLabelFromRecordUsing(fn ($record) => '#'.$record->id.' / '.$record->vehicle->license_plate)->required()->searchable(),
            Select::make('type')->label('Tipo de pedido')->options(['immobilization' => 'Indemnização por imobilização', 'replacement_vehicle' => 'Viatura de substituição'])->required(),
            Select::make('status')->label('Estado')->options(['preparing' => 'Preparação', 'requested' => 'Pedido apresentado', 'awarded' => 'Deferido', 'denied' => 'Indeferido', 'closed' => 'Encerrado'])->default('preparing')->required(),
            DatePicker::make('requested_at')->label('Pedido em'),
            DatePicker::make('resolved_at')->label('Resolvido em')->afterOrEqual('requested_at'),
            TextInput::make('amount_requested')->label('Valor pedido')->numeric()->minValue(0),
            TextInput::make('amount_awarded')->label('Valor atribuído')->numeric()->minValue(0),
            Textarea::make('result')->label('Resultado / observações')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('accident.vehicle.license_plate')->label('Viatura'),
            TextColumn::make('type')->label('Tipo')->formatStateUsing(fn ($state) => $state === 'immobilization' ? 'Indemnização por imobilização' : 'Viatura de substituição'),
            TextColumn::make('status')->label('Estado')->badge(),
            TextColumn::make('requested_at')->label('Pedido em')->date(),
            TextColumn::make('amount_awarded')->label('Valor atribuído')->money('EUR'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlotAccidentRequests::route('/')];
    }
}
