<?php

namespace App\Filament\Resources\RentalVans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RentalVansTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('photos')->label('Fotos')->disk('rental_vans')->limit(1),
            TextColumn::make('name')->label('Carrinha')->searchable()->sortable(),
            TextColumn::make('license_plate')->label('Matrícula')->searchable(),
            TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn ($state) => ['draft' => 'Rascunho', 'published' => 'Publicada', 'archived' => 'Arquivada'][$state]),
            TextColumn::make('self_drive_rate')->label('Sem motorista / h')->money('EUR', divideBy: 100),
            TextColumn::make('with_driver_rate')->label('Com motorista / h')->money('EUR', divideBy: 100),
        ])->filters([SelectFilter::make('status')->label('Estado')->options(['draft' => 'Rascunho', 'published' => 'Publicada', 'archived' => 'Arquivada'])])->recordActions([EditAction::make()]);
    }
}
