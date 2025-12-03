<?php

namespace App\Filament\Resources\Boards;

use App\Filament\Resources\Boards\Pages\CreateBoard;
use App\Filament\Resources\Boards\Pages\EditBoard;
use App\Filament\Resources\Boards\Pages\ListBoards;
use App\Filament\Resources\Boards\Schemas\BoardForm;
use App\Filament\Resources\Boards\Tables\BoardsTable;
use App\Models\Board;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BoardResource extends Resource
{
    protected static ?string $model = Board::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static UnitEnum|string|null $navigationGroup = 'Kanban';

    protected static ?string $recordTitleAttribute = 'Board';

    public static function form(Schema $schema): Schema
    {
        return BoardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BoardsTable::configure($table);
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
            'index' => ListBoards::route('/'),
            'create' => CreateBoard::route('/create'),
            'edit' => EditBoard::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
