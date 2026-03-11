<?php

namespace App\Filament\Resources\ChatBotSettings;

use App\Filament\Resources\ChatBotSettings\Pages\CreateChatBotSetting;
use App\Filament\Resources\ChatBotSettings\Pages\EditChatBotSetting;
use App\Filament\Resources\ChatBotSettings\Pages\ListChatBotSettings;
use App\Filament\Resources\ChatBotSettings\Schemas\ChatBotSettingForm;
use App\Filament\Resources\ChatBotSettings\Tables\ChatBotSettingsTable;
use App\Models\ChatBotSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChatBotSettingResource extends Resource
{
    protected static ?string $model = ChatBotSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ChatBotSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChatBotSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatBotSettings::route('/'),
            'edit' => EditChatBotSetting::route('/{record}/edit'),
            'create' => CreateChatBotSetting::route('/create'),
        ];
    }
}
