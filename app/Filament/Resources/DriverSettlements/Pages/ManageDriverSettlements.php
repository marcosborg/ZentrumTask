<?php

namespace App\Filament\Resources\DriverSettlements\Pages;

use App\Enums\TvdeOperation;
use App\Filament\Resources\DriverSettlements\DriverSettlementResource;
use App\Services\DriverSettlementCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageDriverSettlements extends ManageRecords
{
    protected static string $resource = DriverSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('calculate')->label('Gerar semana SLOT')->schema([
            DatePicker::make('start')->label('Segunda-feira')->required(),
        ])->action(function (array $data): void {
            $end = \Illuminate\Support\Carbon::parse($data['start'])->addDays(6)->toDateString();
            $result = app(DriverSettlementCalculator::class)->calculate($data['start'], $end, null, TvdeOperation::Slot);
            Notification::make()->success()->title($result['created'].' settlements criados; '.$result['missing_profiles'].' perfis em falta')->send();
        })];
    }
}
