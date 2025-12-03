<?php

namespace App\Filament\Resources\TaskAttachments;

use App\Filament\Resources\TaskAttachments\Pages\CreateTaskAttachment;
use App\Filament\Resources\TaskAttachments\Pages\EditTaskAttachment;
use App\Filament\Resources\TaskAttachments\Pages\ListTaskAttachments;
use App\Filament\Resources\TaskAttachments\Schemas\TaskAttachmentForm;
use App\Filament\Resources\TaskAttachments\Tables\TaskAttachmentsTable;
use App\Models\TaskAttachment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TaskAttachmentResource extends Resource
{
    protected static ?string $model = TaskAttachment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    protected static UnitEnum|string|null $navigationGroup = 'Kanban';

    protected static ?string $recordTitleAttribute = 'TaskAttachment';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return TaskAttachmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskAttachmentsTable::configure($table);
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
            'index' => ListTaskAttachments::route('/'),
            'create' => CreateTaskAttachment::route('/create'),
            'edit' => EditTaskAttachment::route('/{record}/edit'),
        ];
    }
}
