<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleAllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Alocacoes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('vehicle_id')
                    ->label('Viatura')
                    ->relationship('vehicle', 'license_plate')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => trim($record->license_plate.' - '.$record->make.' '.$record->model))
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->columnSpan(2),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Ativa',
                        'ended' => 'Terminada',
                        'cancelled' => 'Cancelada',
                    ])
                    ->default('active')
                    ->native(false),
                DateTimePicker::make('starts_at')
                    ->label('Inicio')
                    ->required()
                    ->seconds(false)
                    ->native(false),
                DateTimePicker::make('ends_at')
                    ->label('Fim')
                    ->seconds(false)
                    ->native(false)
                    ->rules(['nullable', 'after_or_equal:starts_at']),
                TextInput::make('start_odometer')
                    ->label('Km inicio')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('end_odometer')
                    ->label('Km fim')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('handover_location')
                    ->label('Local de entrega')
                    ->maxLength(255)
                    ->columnSpan(2),
                Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('starts_at')
            ->columns([
                TextColumn::make('vehicle.license_plate')
                    ->label('Viatura')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle.make')
                    ->label('Marca')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vehicle.model')
                    ->label('Modelo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Fim')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'ended',
                        'gray' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Ativa',
                        'ended' => 'Terminada',
                        'cancelled' => 'Cancelada',
                        default => $state,
                    }),
                TextColumn::make('start_odometer')
                    ->label('Km inicio')
                    ->numeric(),
                TextColumn::make('end_odometer')
                    ->label('Km fim')
                    ->numeric(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\Action::make('endAllocation')
                    ->label('Terminar alocacao')
                    ->icon('heroicon-o-flag')
                    ->form([
                        DateTimePicker::make('ends_at')
                            ->label('Fim')
                            ->default(now())
                            ->seconds(false)
                            ->native(false)
                            ->required(),
                        TextInput::make('end_odometer')
                            ->label('Km fim')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'ends_at' => $data['ends_at'],
                            'end_odometer' => $data['end_odometer'] ?? $record->end_odometer,
                            'status' => 'ended',
                        ]);
                    })
                    ->visible(fn ($record): bool => $record->status === 'active'),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
