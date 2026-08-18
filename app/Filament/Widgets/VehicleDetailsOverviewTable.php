<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VehicleDetailsOverviewTable extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $today = Carbon::today();
                $expiring7 = $today->copy()->addDays(7);
                $expiring60 = $today->copy()->addDays(60);

                return Vehicle::query()
                    ->with(['currentAllocation.driver'])
                    ->withCount([
                        'documents as expired_documents_count' => fn (Builder $query): Builder => $query
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<', $today),
                        'documents as expiring_7_documents_count' => fn (Builder $query): Builder => $query
                            ->whereNotNull('expires_at')
                            ->whereBetween('expires_at', [$today, $expiring7]),
                        'documents as expiring_60_documents_count' => fn (Builder $query): Builder => $query
                            ->whereNotNull('expires_at')
                            ->whereBetween('expires_at', [$today, $expiring60]),
                    ])
                    ->orderBy('license_plate');
            })
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Matricula')
                    ->sortable(),
                TextColumn::make('currentAllocation.driver.name')
                    ->label('Motorista atual')
                    ->placeholder('-'),
                TextColumn::make('currentAllocation.starts_at')
                    ->label('Inicio alocacao')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('expired_documents_count')
                    ->label('Docs expirados')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('expiring_7_documents_count')
                    ->label('Expira 7d')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('expiring_60_documents_count')
                    ->label('Expira 60d')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
            ])
            ->paginated(false);
    }
}
