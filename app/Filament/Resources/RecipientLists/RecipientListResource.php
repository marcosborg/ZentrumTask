<?php

namespace App\Filament\Resources\RecipientLists;

use App\Filament\Resources\RecipientLists\Pages\CreateRecipientList;
use App\Filament\Resources\RecipientLists\Pages\EditRecipientList;
use App\Filament\Resources\RecipientLists\Pages\ListRecipientLists;
use App\Filament\Resources\RecipientLists\Schemas\RecipientListForm;
use App\Filament\Resources\RecipientLists\Tables\RecipientListsTable;
use App\Models\RecipientList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RecipientListResource extends Resource
{
    protected static ?string $model = RecipientList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static UnitEnum|string|null $navigationGroup = 'Kanban';

    protected static ?string $recordTitleAttribute = 'RecipientList';

    public static function form(Schema $schema): Schema
    {
        return RecipientListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipientListsTable::configure($table);
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
            'index' => ListRecipientLists::route('/'),
            'create' => CreateRecipientList::route('/create'),
            'edit' => EditRecipientList::route('/{record}/edit'),
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
