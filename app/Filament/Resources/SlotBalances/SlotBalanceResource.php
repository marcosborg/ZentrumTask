<?php

namespace App\Filament\Resources\SlotBalances;

use App\Enums\TvdeOperation;
use App\Filament\Resources\SlotResource;
use App\Models\DriverParticipation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SlotBalanceResource extends SlotResource
{
    protected static ?string $model = DriverParticipation::class;

    protected static ?string $navigationLabel = 'Saldos e movimentos';

    protected static ?string $pluralModelLabel = 'Saldos e movimentos';

    public static function operation(): TvdeOperation
    {
        return TvdeOperation::Slot;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forOperation(static::operation())->with(['driver', 'balance']);
    }

    public static function table(Table $table): Table
    {

        return $table->columns([

            TextColumn::make('driver.name')->label('Motorista')->searchable(),

            TextColumn::make('starts_at')->label('Entrada')->date('d/m/Y'),

            TextColumn::make('ends_at')->label('Fim')->date('d/m/Y'),

            TextColumn::make('balance.current_balance')->label('Saldo')->money('EUR')->sortable(),

        ])->recordActions([

            Action::make('history')->label('Movimentos')->schema([

                Textarea::make('history')->label('Histórico')->rows(12)->disabled()->default(fn (DriverParticipation $record) => \App\Models\DriverBalanceMovement::query()->where('driver_participation_id', $record->id)->orderByDesc('id')->get()->map(fn ($row) => $row->created_at->format('d/m/Y').' | '.$row->amount.' € | '.$row->description)->implode("\n")),

            ])->modalSubmitAction(false),

            Action::make('regularize')->label('Regularizar')->schema([

                TextInput::make('amount')->label('Valor (positivo para crédito)')->numeric()->required(),

                Textarea::make('description')->label('Motivo / comprovativo')->required(),

            ])->action(fn (DriverParticipation $record, array $data) => app(\App\Services\ParticipationService::class)->regularize($record, (float) $data['amount'], $data['description'])),

        ]);

    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSlotBalances::route('/')];
    }
}
