<?php

namespace App\Filament\Resources\NotificationRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class NotificationRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stage.name')
                    ->sortable(),
                TextColumn::make('messageTemplate.name')
                    ->sortable(),
                TextColumn::make('recipientList.name')
                    ->sortable(),
                TextColumn::make('trigger')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'on_enter_stage' => 'On Enter Stage',
                        'on_exit_stage' => 'On Exit Stage',
                        'on_task_update' => 'On Task Update',
                        default => $state,
                    }),
                TextColumn::make('send_mode')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'always' => 'Always',
                        'only_if_no_response' => 'Only If No Response',
                        default => $state,
                    }),
                TextColumn::make('cooldown_hours')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('also_send_to_assigned_user')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
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
