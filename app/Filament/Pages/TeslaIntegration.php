<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class TeslaIntegration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Tesla';

    protected static ?string $title = 'Integracao Tesla';

    protected static UnitEnum|string|null $navigationGroup = 'Administracao';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'tesla-integration';

    protected string $view = 'filament.pages.tesla-integration';

    public static function getNavigationUrl(): string
    {
        return route('admin.tesla.index');
    }
}
