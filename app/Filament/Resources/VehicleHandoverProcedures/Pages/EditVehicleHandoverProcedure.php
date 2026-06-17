<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Pages;

use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVehicleHandoverProcedure extends EditRecord
{
    protected static string $resource = VehicleHandoverProcedureResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var VehicleHandoverProcedure $record */
        $record = $this->record;

        $data['selection_mode'] = 'vehicle';
        $data['general_photos'] = $record->general_photo_paths ?? [];

        foreach (($record->guided_photo_items ?? []) as $key => $item) {
            $data['guided_photo_items'][$key]['photo'] = $item['photo_path'] ?? null;
        }

        foreach (($record->video_items ?? []) as $key => $item) {
            $data['video_items'][$key] = $item['video_path'] ?? null;
        }

        $data['damage_items'] = collect($record->damage_items ?? [])
            ->map(function (array $item): array {
                $item['photo'] = $item['photo_path'] ?? null;

                return $item;
            })
            ->values()
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var VehicleHandoverProcedureService $service */
        $service = app(VehicleHandoverProcedureService::class);

        /** @var VehicleHandoverProcedure $record */
        return $service->update($record, $data, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
