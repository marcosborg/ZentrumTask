<?php

namespace App\Filament\Resources\Tasks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->modifyQueryUsing(function (Builder $query): void {
                $query->withExists([
                    'notificationLogs as email_sent' => function (Builder $subQuery): void {
                        $subQuery->where('status', 'sent');
                    },
                ]);
            })
            ->columns([
                TextColumn::make('board.name')
                    ->sortable(),
                TextColumn::make('stage.name')
                    ->sortable(),
                TextColumn::make('assignedTo.name')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('email_sent')
                    ->label('Email')
                    ->badge()
                    ->state(fn (Model $record): string => $record->email_sent ? 'Enviado' : 'Por enviar')
                    ->colors([
                        'success' => fn (Model $record): bool => (bool) $record->email_sent,
                        'warning' => fn (Model $record): bool => ! (bool) $record->email_sent,
                    ]),
                TextColumn::make('priority')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'normal' => 'Normal',
                        'medium' => 'Medium',
                        'high' => 'High',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('due_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('external_reference')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
