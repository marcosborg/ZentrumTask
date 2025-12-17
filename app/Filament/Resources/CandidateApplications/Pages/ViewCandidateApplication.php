<?php

namespace App\Filament\Resources\CandidateApplications\Pages;

use App\Filament\Resources\CandidateApplications\CandidateApplicationResource;
use App\Filament\Resources\Drivers\DriverResource;
use App\Models\Driver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCandidateApplication extends ViewRecord
{
    protected static string $resource = CandidateApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createDriver')
                ->label('Criar Driver')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => $this->record?->status === 'submitted')
                ->action(function (): void {
                    $driver = Driver::create([
                        'name' => $this->record->full_name,
                        'email' => $this->record->email,
                        'phone' => $this->record->phone,
                        'nif' => $this->record->nif,
                        'notes' => 'Criado a partir da candidatura '.$this->record->id,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Driver criado')
                        ->body('Registo criado a partir da candidatura.')
                        ->send();

                    $this->redirect(DriverResource::getUrl('edit', ['record' => $driver]));
                }),
        ];
    }
}
