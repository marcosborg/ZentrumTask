<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class MaintenanceToggle extends Page
{
    protected static ?string $navigationLabel = 'Manutenção';

    protected static ?string $title = 'Manutenção';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    protected string $view = 'filament.pages.maintenance-toggle';

    public bool $isDown = false;

    public string $secret;

    public function mount(): void
    {
        $this->isDown = app()->isDownForMaintenance();
        $this->secret = config('app.maintenance_secret', env('MAINTENANCE_SECRET', 'filament-secret'));
    }

    public function toggleMaintenance(): void
    {
        if ($this->isDown) {
            Artisan::call('up');
            $this->isDown = false;
            Notification::make()
                ->title('Site de volta online')
                ->success()
                ->send();
        } else {
            Artisan::call('down', [
                '--render' => 'errors.503',
                '--secret' => $this->secret,
            ]);
            $this->isDown = true;
            Notification::make()
                ->title('Site em manutenção')
                ->body("Aceda com ?secret={$this->secret} para bypass.")
                ->danger()
                ->send();
        }
    }
}
