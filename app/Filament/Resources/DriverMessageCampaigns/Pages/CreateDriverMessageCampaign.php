<?php

namespace App\Filament\Resources\DriverMessageCampaigns\Pages;

use App\Filament\Resources\DriverMessageCampaigns\DriverMessageCampaignResource;
use App\Jobs\SendDriverCampaignEmail;
use App\Models\Driver;
use App\Models\DriverMessageCampaign;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateDriverMessageCampaign extends CreateRecord
{
    protected static string $resource = DriverMessageCampaignResource::class;

    protected static ?string $title = 'Nova mensagem aos motoristas';

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Enviar emails');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $driverIds = array_map('intval', $data['driver_ids']);
        unset($data['driver_ids']);

        $deliveriesToDispatch = [];

        $campaign = DB::transaction(function () use ($data, $driverIds, &$deliveriesToDispatch): DriverMessageCampaign {
            $campaign = DriverMessageCampaign::query()->create([...$data, 'created_by_user_id' => auth()->id()]);

            Driver::query()->whereKey($driverIds)->orderBy('name')->get()
                ->each(function (Driver $driver) use ($campaign, &$deliveriesToDispatch): void {
                    $delivery = $campaign->deliveries()->create([
                        'driver_id' => $driver->id,
                        'driver_name' => $driver->name,
                        'email_address' => $driver->email,
                        'phone_number' => $driver->phone,
                        'email_status' => filled($driver->email) ? 'pending' : 'unavailable',
                        'whatsapp_status' => filled($driver->phone) ? 'pending' : 'unavailable',
                    ]);

                    if (filled($driver->email)) {
                        $deliveriesToDispatch[] = $delivery->id;
                    }
                });

            return $campaign;
        });

        foreach ($deliveriesToDispatch as $deliveryId) {
            SendDriverCampaignEmail::dispatch($deliveryId);
        }

        return $campaign;
    }

    protected function getRedirectUrl(): string
    {
        return DriverMessageCampaignResource::getUrl('view', ['record' => $this->record]);
    }
}
