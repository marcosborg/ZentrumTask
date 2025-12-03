<?php

namespace App\Filament\Resources\TaskComments;

use App\Filament\Resources\TaskComments\Pages\CreateTaskComment;
use App\Filament\Resources\TaskComments\Pages\EditTaskComment;
use App\Filament\Resources\TaskComments\Pages\ListTaskComments;
use App\Filament\Resources\TaskComments\Schemas\TaskCommentForm;
use App\Filament\Resources\TaskComments\Tables\TaskCommentsTable;
use App\Models\TaskComment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaskCommentResource extends Resource
{
    protected static ?string $model = TaskComment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $recordTitleAttribute = 'TaskComment';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return TaskCommentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskCommentsTable::configure($table);
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
            'index' => ListTaskComments::route('/'),
            'create' => CreateTaskComment::route('/create'),
            'edit' => EditTaskComment::route('/{record}/edit'),
        ];
    }
}
