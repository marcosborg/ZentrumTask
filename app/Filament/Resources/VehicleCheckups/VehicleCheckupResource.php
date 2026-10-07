<?php

namespace App\Filament\Resources\VehicleCheckups;

use App\Filament\Resources\SlotResource;
use App\Filament\Resources\VehicleCheckups\Pages\ManageVehicleCheckups;
use App\Models\VehicleCheckup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleCheckupResource extends SlotResource
{
    protected static ?string $model = VehicleCheckup::class;

    protected static ?string $navigationLabel = 'Oficina / check-ups';

    protected static ?string $modelLabel = 'Oficina / check-ups';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('vehicle', fn ($q) => $q->where('operation', 'slot'))->with(['vehicle', 'responsible']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('vehicle_id')->label('Viatura SLOT')->relationship('vehicle', 'license_plate', fn (Builder $query) => $query->where('operation', 'slot'))->searchable()->required(),
            DateTimePicker::make('scheduled_at')->label('Agendado para')->timezone(config('slots.timezone')),
            DateTimePicker::make('completed_at')->label('Concluído em')->timezone(config('slots.timezone')),
            Select::make('responsible_user_id')->label('Responsável')->relationship('responsible', 'name')->searchable(),
            Textarea::make('notes')->label('Observações')->columnSpanFull(),
            FileUpload::make('attachments')->label('Anexos')->multiple()->disk('local')->directory('slot-checkups')->visibility('private')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('vehicle.license_plate')->label('Viatura')->searchable(),
            TextColumn::make('scheduled_at')->label('Agendamento')->dateTime('d/m/Y H:i', config('slots.timezone')),
            TextColumn::make('completed_at')->label('Concluído')->dateTime('d/m/Y H:i', config('slots.timezone')),
            TextColumn::make('responsible.name')->label('Responsável'),
            TextColumn::make('next_due')->label('Próximo check-up')->state(fn (VehicleCheckup $record) => $record->nextDueAt()?->format('d/m/Y') ?? 'Inicial pendente'),
            TextColumn::make('priority')->label('Atendimento')->state('Prioritário / preços descontados'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVehicleCheckups::route('/')];
    }
}
