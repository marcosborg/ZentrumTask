<?php

namespace App\Filament\Resources\Stages\Schemas;

use App\Models\Stage;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estagio')
                    ->schema([
                        Select::make('board_id')
                            ->label('Board')
                            ->relationship('board', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('slug')
                            ->required(),
                        ColorPicker::make('color')
                            ->default(null),
                        Toggle::make('is_initial')
                            ->required(),
                        Toggle::make('is_final')
                            ->required(),
                        Toggle::make('freeze_sla')
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Timeout automatico')
                    ->schema([
                        TextInput::make('timeout_days')
                            ->label('Timeout (dias)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->nullable(),
                        Select::make('timeout_target_stage_id')
                            ->label('Mover para')
                            ->options(fn (Get $get, ?Stage $record): array => Stage::query()
                                ->where('board_id', $get('board_id'))
                                ->when($record?->id, fn ($query, int $stageId) => $query->whereKeyNot($stageId))
                                ->orderBy('position')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled(fn (Get $get): bool => ! $get('board_id')),
                    ])
                    ->columns(2),
            ]);
    }
}
