<?php

namespace App\Filament\Resources\NotificationRules;

use App\Filament\Resources\NotificationRules\Pages\CreateNotificationRule;
use App\Filament\Resources\NotificationRules\Pages\EditNotificationRule;
use App\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use App\Filament\Resources\NotificationRules\Schemas\NotificationRuleForm;
use App\Filament\Resources\NotificationRules\Tables\NotificationRulesTable;
use App\Models\NotificationRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class NotificationRuleResource extends Resource
{
    protected static ?string $model = NotificationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static UnitEnum|string|null $navigationGroup = 'Kanban';

    protected static ?string $recordTitleAttribute = 'NotificationRule';

    public static function form(Schema $schema): Schema
    {
        return NotificationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationRulesTable::configure($table);
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
            'index' => ListNotificationRules::route('/'),
            'create' => CreateNotificationRule::route('/create'),
            'edit' => EditNotificationRule::route('/{record}/edit'),
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
