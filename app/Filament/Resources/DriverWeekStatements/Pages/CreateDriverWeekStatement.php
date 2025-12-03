<?php

namespace App\Filament\Resources\DriverWeekStatements\Pages;

use App\Filament\Resources\DriverWeekStatements\DriverWeekStatementResource;
use App\Filament\Resources\DriverWeekStatements\Schemas\DriverWeekStatementInputForm;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverWeekStatement;
use App\Services\DriverSettlementService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class CreateDriverWeekStatement extends CreateRecord
{
    protected static string $resource = DriverWeekStatementResource::class;

    public function form(Schema $schema): Schema
    {
        return DriverWeekStatementInputForm::configure($schema);
    }

    protected function handleRecordCreation(array $data): DriverWeekStatement
    {
        $driver = Driver::findOrFail($data['driver_id']);

        $profileId = $data['billing_profile_id']
            ?? DriverBillingProfile::query()
                ->active()
                ->where('driver_id', $driver->id)
                ->orderByDesc('valid_from')
                ->value('id');

        if (! $profileId) {
            throw ValidationException::withMessages([
                'billing_profile_id' => 'Selecione um perfil de faturação ativo para o motorista.',
            ]);
        }

        $profile = DriverBillingProfile::findOrFail($profileId);

        $statement = app(DriverSettlementService::class)
            ->createStatementFromInputs($driver, $profile, $data);

        $this->record = $statement;

        return $statement;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
