<?php

namespace App\Filament\Resources\SlotWeekStatements\Pages;

use App\Filament\Resources\SlotWeekStatements\SlotWeekStatementResource;
use App\Models\DriverParticipation;
use App\Services\DriverSettlementService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlotWeekStatements extends ManageRecords
{
    protected static string $resource = SlotWeekStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(function (array $data): \App\Models\DriverWeekStatement {
            $participation = DriverParticipation::query()->where('operation', 'slot')->findOrFail($data['driver_participation_id']);
            $end = \Illuminate\Support\Carbon::parse($data['week_start_date'])->addDays(6)->toDateString();
            $profile = $participation->billingProfiles()->active()->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $data['week_start_date']))->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $end))->latest('valid_from')->firstOrFail();

            return app(DriverSettlementService::class)->createStatementFromInputs($participation->driver, $profile, $data + ['week_end_date' => $end]);
        })];
    }
}
