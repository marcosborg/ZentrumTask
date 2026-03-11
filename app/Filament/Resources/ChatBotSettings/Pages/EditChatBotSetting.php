<?php

namespace App\Filament\Resources\ChatBotSettings\Pages;

use App\Filament\Resources\ChatBotSettings\ChatBotSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChatBotSetting extends EditRecord
{
    protected static string $resource = ChatBotSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
