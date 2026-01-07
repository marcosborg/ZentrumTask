<?php

namespace App\Filament\Widgets;

use App\Models\VehicleDocumentAlert;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class VehicleDocumentAlertsUrgentTable extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => VehicleDocumentAlert::query()
                ->with(['document.vehicle'])
                ->where('is_resolved', false)
                ->orderByRaw("case level when 'expired' then 1 when 'expiring_7' then 2 when 'expiring_30' then 3 else 4 end")
                ->orderByRaw('(select expires_at from vehicle_documents where vehicle_documents.id = vehicle_document_alerts.vehicle_document_id) asc')
                ->limit(20))
            ->columns([
                TextColumn::make('document.vehicle.license_plate')
                    ->label('Matricula')
                    ->sortable(),
                TextColumn::make('document.title')
                    ->label('Documento')
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Nivel')
                    ->badge()
                    ->colors([
                        'danger' => 'expired',
                        'warning' => ['expiring_7', 'expiring_30'],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'expired' => 'Expirado',
                        'expiring_7' => 'Expira em 7 dias',
                        'expiring_30' => 'Expira em 30 dias',
                        default => $state,
                    }),
                TextColumn::make('document.expires_at')
                    ->label('Validade')
                    ->date()
                    ->sortable(),
                TextColumn::make('message')
                    ->label('Mensagem')
                    ->wrap()
                    ->toggleable(),
            ])
            ->paginated(false);
    }
}
