<?php

namespace App\Filament\Pages;

use App\Models\TeslaAccount;
use App\Models\TeslaVehicle;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class TeslaIntegration extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Tesla';

    protected static ?string $title = 'Integracao Tesla';

    protected static UnitEnum|string|null $navigationGroup = 'Administracao';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'tesla';

    protected string $view = 'filament.pages.tesla-integration';

    public bool $isConfigured = false;

    public Collection $accounts;

    public Collection $vehicles;

    public function mount(): void
    {
        $this->isConfigured = filled(config('services.tesla.client_id'))
            && filled(config('services.tesla.client_secret'))
            && filled(config('services.tesla.redirect_uri'));

        $this->accounts = TeslaAccount::query()
            ->withCount('vehicles')
            ->latest()
            ->get();

        $this->vehicles = TeslaVehicle::query()
            ->with('account')
            ->withCount(['snapshots', 'chargingEvents', 'errors'])
            ->latest('last_seen_at')
            ->latest()
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TeslaVehicle::query()
                    ->with('account')
                    ->withCount(['snapshots', 'chargingEvents', 'errors'])
                    ->latest('last_seen_at')
            )
            ->columns([
                TextColumn::make('display_name')
                    ->label('Viatura')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->weight('semibold'),
                TextColumn::make('vin')
                    ->label('VIN')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('state')
                    ->label('Estado')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        'online' => 'success',
                        'asleep' => 'warning',
                        'offline' => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'online' => 'Online',
                        'asleep' => 'Sleep',
                        'offline' => 'Offline',
                        default => $state ? ucfirst($state) : '-',
                    }),
                TextColumn::make('battery_level')
                    ->label('Bateria')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : "{$state}%")
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state <= 15 => 'danger',
                        $state <= 35 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('odometer')
                    ->label('Odometro')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '-' : number_format($state, 1, ',', ' ')),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->label('Ultima atualizacao')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('snapshots_count')
                    ->label('Snapshots')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('charging_events_count')
                    ->label('Carreg.')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('errors_count')
                    ->label('Erros')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Estado')
                    ->options([
                        'online' => 'Online',
                        'asleep' => 'Sleep',
                        'offline' => 'Offline',
                    ]),
            ])
            ->recordUrl(fn (TeslaVehicle $record): string => TeslaVehicleDetails::getUrl(['vehicle' => $record]))
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
