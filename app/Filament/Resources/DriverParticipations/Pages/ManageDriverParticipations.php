<?php

namespace App\Filament\Resources\DriverParticipations\Pages;

use App\Filament\Resources\DriverParticipations\DriverParticipationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDriverParticipations extends ManageRecords
{
    protected static string $resource = DriverParticipationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(function (array $data): array {
                $data['operation'] = DriverParticipationResource::operation()->value;
                $data['status'] = 'preparing';

                return $data;
            }),
        ];
    }
}
