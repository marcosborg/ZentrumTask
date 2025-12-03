<?php

namespace App\Filament\Resources\DriverWeekStatements;

use App\Filament\Resources\DriverWeekStatements\Pages\CreateDriverWeekStatement;
use App\Filament\Resources\DriverWeekStatements\Pages\EditDriverWeekStatement;
use App\Filament\Resources\DriverWeekStatements\Pages\ListDriverWeekStatements;
use App\Filament\Resources\DriverWeekStatements\Pages\ViewDriverWeekStatement;
use App\Filament\Resources\DriverWeekStatements\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\DriverWeekStatements\Schemas\DriverWeekStatementForm;
use App\Filament\Resources\DriverWeekStatements\Schemas\DriverWeekStatementInfolist;
use App\Filament\Resources\DriverWeekStatements\Tables\DriverWeekStatementsTable;
use App\Models\DriverWeekStatement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DriverWeekStatementResource extends Resource
{
    protected static ?string $model = DriverWeekStatement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $recordTitleAttribute = 'week_label';

    public static function form(Schema $schema): Schema
    {
        return DriverWeekStatementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DriverWeekStatementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriverWeekStatementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverWeekStatements::route('/'),
            'create' => CreateDriverWeekStatement::route('/create'),
            'view' => ViewDriverWeekStatement::route('/{record}'),
            'edit' => EditDriverWeekStatement::route('/{record}/edit'),
        ];
    }
}
