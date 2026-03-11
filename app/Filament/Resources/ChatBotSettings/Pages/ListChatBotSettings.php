<?php

namespace App\Filament\Resources\ChatBotSettings\Pages;

use App\Filament\Resources\ChatBotSettings\ChatBotSettingResource;
use App\Models\ChatBotSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChatBotSettings extends ListRecords
{
    protected static string $resource = ChatBotSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ChatBotSetting::query()->count() === 0),
        ];
    }
}
