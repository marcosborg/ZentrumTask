<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\Schemas\VehicleInitialPhotosForm;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditVehicleInitialPhotos extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Fotografias iniciais';
    }

    public function form(Schema $schema): Schema
    {
        return VehicleInitialPhotosForm::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEdit')
                ->label('Voltar a viatura')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(VehicleResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
