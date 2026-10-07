<?php

namespace App\Filament\Resources\SlotAccidents;

use App\Filament\Resources\SlotAccidents\Pages\ManageSlotAccidents;
use App\Filament\Resources\SlotResource;
use App\Models\SlotAccident;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SlotAccidentResource extends SlotResource
{
    protected static ?string $model = SlotAccident::class;

    protected static ?string $navigationLabel = 'Acidentes SLOT';

    protected static ?string $modelLabel = 'Acidentes SLOT';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('participation', fn ($q) => $q->where('operation', 'slot'))->with(['participation.driver', 'vehicle']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('driver_participation_id')->label('Participação SLOT')->relationship('participation', 'id', fn (Builder $query) => $query->where('operation', 'slot'))->getOptionLabelFromRecordUsing(fn ($record) => $record->driver->name.' / '.$record->starts_at->format('d/m/Y'))->searchable()->required(),
            Select::make('vehicle_id')->label('Viatura')->relationship('vehicle', 'license_plate', fn (Builder $query) => $query->where('operation', 'slot'))->required(),
            DateTimePicker::make('occurred_at')->label('Acidente em')->timezone(config('slots.timezone'))->required(),
            Select::make('status')->label('Estado')->options(['open' => 'Aberto', 'following' => 'Em acompanhamento', 'closed' => 'Encerrado'])->default('open')->required(),
            TextInput::make('insurer')->label('Seguradora'),
            TextInput::make('reference')->label('Referência'),
            Select::make('responsible_user_id')->label('Responsável')->options(\App\Models\User::query()->pluck('name', 'id')),
            Textarea::make('contacts')->label('Contactos e registo do acompanhamento')->columnSpanFull(),
            DateTimePicker::make('immobilized_at')->label('Imobilização')->timezone(config('slots.timezone')),
            DateTimePicker::make('repair_started_at')->label('Início reparação')->timezone(config('slots.timezone')),
            DateTimePicker::make('repair_completed_at')->label('Fim reparação')->timezone(config('slots.timezone'))->afterOrEqual('repair_started_at'),
            Textarea::make('notes')->label('Observações')->columnSpanFull(),
            FileUpload::make('documents')->label('Documentos')->multiple()->disk('local')->directory('slot-accidents')->visibility('private'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('participation.driver.name')->label('Motorista')->searchable(),
            TextColumn::make('vehicle.license_plate')->label('Viatura'),
            TextColumn::make('occurred_at')->label('Acidente')->dateTime('d/m/Y'),
            TextColumn::make('insurer')->label('Seguradora'),
            TextColumn::make('status')->label('Estado')->badge(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlotAccidents::route('/')];
    }
}
