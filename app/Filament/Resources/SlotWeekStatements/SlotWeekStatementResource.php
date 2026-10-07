<?php

namespace App\Filament\Resources\SlotWeekStatements;

use App\Enums\TvdeOperation;
use App\Filament\Resources\SlotResource;
use App\Filament\Resources\SlotWeekStatements\Pages\ManageSlotWeekStatements;
use App\Models\DriverWeekStatement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SlotWeekStatementResource extends SlotResource
{
    protected static ?string $model = DriverWeekStatement::class;

    protected static ?string $navigationLabel = 'Extratos manuais SLOT';

    protected static ?string $modelLabel = 'extrato manual SLOT';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forOperation(TvdeOperation::Slot)->with('driver');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('driver_participation_id')->label('Participação SLOT')->options(\App\Models\DriverParticipation::query()->forOperation(TvdeOperation::Slot)->with('driver')->get()->mapWithKeys(fn ($row) => [$row->id => $row->driver->name.' / '.$row->starts_at->format('d/m/Y')]))->required(),
            DatePicker::make('week_start_date')->label('Segunda-feira')->required(),
            TextInput::make('net_total')->label('Ganhos (já inclui gorjetas)')->numeric()->required(),
            TextInput::make('tips_total')->label('Gorjetas incluídas nos ganhos')->numeric()->default(0),
            TextInput::make('expenses_total')->label('Ajustes documentados')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('driver.name')->label('Motorista'),
            TextColumn::make('week_start_date')->label('Semana')->date(),
            TextColumn::make('rules_snapshot.slot_pack_name')->label('Pack'),
            TextColumn::make('net_total')->label('Ganhos')->money('EUR'),
            TextColumn::make('slot_fee')->label('Pack (IVA incluído)')->money('EUR'),
            TextColumn::make('amount_payable_to_driver')->label('Valor da semana')->money('EUR'),
            TextColumn::make('status')->label('Estado'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlotWeekStatements::route('/')];
    }
}
