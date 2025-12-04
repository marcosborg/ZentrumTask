<?php

namespace App\Filament\Resources\WebsiteMenuItems;

use App\Filament\Resources\WebsiteMenuItems\Pages\CreateWebsiteMenuItem;
use App\Filament\Resources\WebsiteMenuItems\Pages\EditWebsiteMenuItem;
use App\Filament\Resources\WebsiteMenuItems\Pages\ListWebsiteMenuItems;
use App\Filament\Resources\WebsiteMenuItems\Schemas\WebsiteMenuItemForm;
use App\Filament\Resources\WebsiteMenuItems\Tables\WebsiteMenuItemsTable;
use App\Models\WebsiteMenuItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WebsiteMenuItemResource extends Resource
{
    protected static ?string $model = WebsiteMenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'label';

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Menu';

    public static function form(Schema $schema): Schema
    {
        return WebsiteMenuItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebsiteMenuItemsTable::configure($table);
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
            'index' => ListWebsiteMenuItems::route('/'),
            'create' => CreateWebsiteMenuItem::route('/create'),
            'edit' => EditWebsiteMenuItem::route('/{record}/edit'),
        ];
    }
}
