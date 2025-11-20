<?php

namespace App\Filament\Resources\NotificationLogs\Pages;

use App\Filament\Resources\NotificationLogs\NotificationLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNotificationLog extends EditRecord
{
    protected static string $resource = NotificationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
