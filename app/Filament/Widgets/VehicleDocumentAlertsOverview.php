<?php

namespace App\Filament\Widgets;

use App\Models\VehicleDocument;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class VehicleDocumentAlertsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $expiring7 = $today->copy()->addDays(7);
        $expiring60 = $today->copy()->addDays(60);

        $expiredCount = VehicleDocument::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $today)
            ->count();

        $expiring7Count = VehicleDocument::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$today, $expiring7])
            ->count();

        $expiring60Count = VehicleDocument::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$today, $expiring60])
            ->count();

        return [
            Stat::make('Documentos expirados', $expiredCount)
                ->color('danger'),
            Stat::make('Expiram em 7 dias', $expiring7Count)
                ->color('warning'),
            Stat::make('Expiram em 60 dias', $expiring60Count)
                ->color('warning'),
        ];
    }
}
