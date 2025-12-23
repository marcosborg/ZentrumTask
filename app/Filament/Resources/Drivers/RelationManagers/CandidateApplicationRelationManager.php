<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use App\Filament\Resources\CandidateApplications\CandidateApplicationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidateApplicationRelationManager extends RelationManager
{
    protected static string $relationship = 'candidateApplication';

    protected static ?string $title = 'Candidatura';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nome'),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('phone')
                    ->label('Telemovel'),
                TextColumn::make('ver')
                    ->label('Detalhe')
                    ->formatStateUsing(fn (): string => 'Ver candidatura')
                    ->url(fn ($record) => CandidateApplicationResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'success' => 'submitted',
                        'warning' => 'incomplete',
                        'gray' => 'draft',
                    ]),
            ])
            ->paginated(false);
    }
}
