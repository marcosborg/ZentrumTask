<?php

namespace App\Filament\Resources\RentalParticipations\Pages;

use App\Filament\Resources\RentalParticipations\RentalParticipationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRentalParticipations extends ManageRecords
{
    protected static string $resource = RentalParticipationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data): array {
            $data['operation'] = 'rental';
            $data['status'] = 'preparing';

            return $data;
        })];
    }
}
