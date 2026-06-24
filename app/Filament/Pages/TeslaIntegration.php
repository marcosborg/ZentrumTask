<?php

namespace App\Filament\Pages;

use App\Models\TeslaAccount;
use App\Models\TeslaVehicle;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class TeslaIntegration extends Page
{
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
            ->latest('last_seen_at')
            ->latest()
            ->get();
    }
}
