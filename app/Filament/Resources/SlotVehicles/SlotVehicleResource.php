<?php

namespace App\Filament\Resources\SlotVehicles;

use App\Enums\TvdeOperation;
use App\Filament\Resources\SlotResource;
use App\Filament\Resources\SlotVehicles\Pages\ManageSlotVehicles;
use App\Filament\Resources\Vehicles\Schemas\VehicleForm;
use App\Models\Driver;
use App\Models\Vehicle;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SlotVehicleResource extends SlotResource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationLabel = 'Viaturas SLOT';

    protected static ?string $modelLabel = 'viatura SLOT';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forOperation(TvdeOperation::Slot)->with('owner');
    }

    public static function form(Schema $schema): Schema
    {
        $schema = VehicleForm::configure($schema);

        return $schema->components([
            Hidden::make('operation')->default('slot'),
            Select::make('owner_driver_id')->label('Motorista proprietário')->options(Driver::query()->pluck('name', 'id'))->searchable()->required(),
            ...$schema->getComponents(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('license_plate')->label('Matrícula')->searchable(),
            TextColumn::make('owner.name')->label('Proprietário'),
            TextColumn::make('make')->label('Marca'),
            TextColumn::make('model')->label('Modelo'),
            TextColumn::make('status')->label('Estado'),
            TextColumn::make('next_checkup')->label('Próximo check-up')->state(fn (Vehicle $record) => $record->checkups()->whereNotNull('completed_at')->latest('completed_at')->first()?->nextDueAt()?->format('d/m/Y') ?? 'Inicial pendente'),
        ])->recordActions([EditAction::make()->url(fn (Vehicle $record) => static::getUrl('edit', ['record' => $record]))]);
    }

    public static function getRelations(): array
    {
        return [\App\Filament\Resources\Vehicles\RelationManagers\VehicleDocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlotVehicles::route('/'), 'edit' => \App\Filament\Resources\SlotVehicles\Pages\EditSlotVehicle::route('/{record}/edit')];
    }
}
