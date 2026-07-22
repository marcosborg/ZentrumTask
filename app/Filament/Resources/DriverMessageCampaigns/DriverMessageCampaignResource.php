<?php

namespace App\Filament\Resources\DriverMessageCampaigns;

use App\Filament\Resources\DriverMessageCampaigns\Pages\CreateDriverMessageCampaign;
use App\Filament\Resources\DriverMessageCampaigns\Pages\ListDriverMessageCampaigns;
use App\Filament\Resources\DriverMessageCampaigns\Pages\ViewDriverMessageCampaign;
use App\Filament\Resources\DriverMessageCampaigns\Schemas\DriverMessageCampaignForm;
use App\Filament\Resources\DriverMessageCampaigns\Schemas\DriverMessageCampaignInfolist;
use App\Filament\Resources\DriverMessageCampaigns\Tables\DriverMessageCampaignsTable;
use App\Models\DriverMessageCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DriverMessageCampaignResource extends Resource
{
    protected static ?string $model = DriverMessageCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $navigationLabel = 'Mensagens aos motoristas';

    protected static ?string $modelLabel = 'mensagem aos motoristas';

    protected static ?string $pluralModelLabel = 'mensagens aos motoristas';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return DriverMessageCampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DriverMessageCampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriverMessageCampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverMessageCampaigns::route('/'),
            'create' => CreateDriverMessageCampaign::route('/create'),
            'view' => ViewDriverMessageCampaign::route('/{record}'),
        ];
    }
}
