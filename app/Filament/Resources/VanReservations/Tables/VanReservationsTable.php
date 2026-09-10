<?php

namespace App\Filament\Resources\VanReservations\Tables;

use App\Models\VanReservation;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VanReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->label('Referência')->searchable(),
            TextColumn::make('van.name')->label('Carrinha')->searchable(),
            TextColumn::make('name')->label('Cliente')->searchable(),
            TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn ($state) => VanReservation::STATUSES[$state]),
            TextColumn::make('starts_at')->label('Início')->dateTime('d/m/Y H:i', timezone: 'Europe/Lisbon')->sortable(),
            TextColumn::make('ends_at')->label('Fim')->dateTime('d/m/Y H:i', timezone: 'Europe/Lisbon'),
            TextColumn::make('estimated_total')->label('Estimativa')->money('EUR', divideBy: 100),
            TextColumn::make('kanban_error')->label('Kanban')->limit(35)->placeholder('Integrado'),
        ])->filters([
            SelectFilter::make('status')->label('Estado')->options(VanReservation::STATUSES),
            SelectFilter::make('rental_van_id')->label('Carrinha')->relationship('van', 'name'),
            Filter::make('kanban_failed')->label('Falha no Kanban')->query(fn ($query) => $query->whereNotNull('kanban_error')),
        ])->recordActions([EditAction::make()->label('Gerir')])->defaultSort('created_at', 'desc');
    }
}
