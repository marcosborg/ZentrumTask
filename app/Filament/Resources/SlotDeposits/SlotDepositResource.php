<?php

namespace App\Filament\Resources\SlotDeposits;

use App\Filament\Resources\SlotBalances\SlotBalanceResource;
use App\Models\DriverParticipation;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlotDepositResource extends SlotBalanceResource
{
    protected static ?string $navigationLabel = 'Cauções';

    protected static ?string $pluralModelLabel = 'Cauções';

    public static function table(Table $table): Table
    {

        return $table->columns([

            TextColumn::make('driver.name')->label('Motorista')->searchable(),

            TextColumn::make('starts_at')->label('Entrada')->date('d/m/Y'),

            TextColumn::make('deposit_initial_amount')->label('Acordada')->money('EUR'),

            TextColumn::make('deposit_amount')->label('Entrega inicial')->money('EUR'),

            TextColumn::make('deposit_balance')->label('Saldo da caução')->state(fn (DriverParticipation $record) => app(\App\Services\DriverDepositService::class)->summaryForDriver($record->driver, $record->id)['current_balance'])->money('EUR'),

        ])->recordActions([

            Action::make('history')->label('Histórico')->schema([

                Textarea::make('history')->label('Movimentos')->rows(12)->disabled()->default(fn (DriverParticipation $record) => collect(app(\App\Services\DriverDepositService::class)->historyForDriver($record->driver, $record->id))->map(fn ($row) => ($row['occurred_at']?->format('d/m/Y') ?? '-').' | '.$row['amount'].' € | '.$row['description'])->implode("\n")),

            ])->modalSubmitAction(false),

            Action::make('movement')->label('Movimento de caução')->schema([

                DatePicker::make('occurred_at')->label('Data')->required(),

                TextInput::make('amount')->label('Débito positivo / recebimento negativo')->numeric()->required(),

                Textarea::make('description')->label('Motivo / comprovativo')->required(),

            ])->action(fn (DriverParticipation $record, array $data) => \App\Models\DriverDepositDebit::query()->create($data + ['driver_id' => $record->driver_id, 'driver_participation_id' => $record->id, 'operation' => $record->operation->value, 'created_by_user_id' => auth()->id(), 'source_file' => 'manual'])),

        ]);

    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSlotDeposits::route('/')];
    }
}
