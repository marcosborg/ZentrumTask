<?php

namespace App\Filament\Resources\AdvertisingOpportunities;

use App\Filament\Resources\AdvertisingOpportunities\Pages\ManageAdvertisingOpportunities;
use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdvertisingOpportunityResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $modelLabel = 'oportunidade';

    protected static ?string $pluralModelLabel = 'oportunidades';

    protected static string|\UnitEnum|null $navigationGroup = 'Zentrum Ads';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('board', fn (Builder $query): Builder => $query->where('slug', 'zentrum-ads'));
    }

    public static function form(Schema $schema): Schema
    {
        $boardId = Board::query()->where('slug', 'zentrum-ads')->value('id');

        return $schema->components([
            TextInput::make('title')->label('Anunciante')->required()->maxLength(200),
            Select::make('stage_id')
                ->label('Estado')
                ->options(Stage::query()->where('board_id', $boardId)->orderBy('position')->pluck('name', 'id'))
                ->default(fn () => Stage::query()->where('board_id', $boardId)->where('is_initial', true)->value('id'))
                ->exists('stages', 'id', modifyRuleUsing: fn ($rule) => $rule->where('board_id', $boardId))
                ->required(),
            Select::make('assigned_to_id')->label('Responsável comercial')->relationship('assignedTo', 'name')->searchable()->preload(),
            DateTimePicker::make('due_at')->label('Próximo contacto'),
            Textarea::make('description')->label('Pedido e contactos')->rows(9)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('title')->label('Anunciante')->searchable(),
            TextColumn::make('stage.name')->label('Estado'),
            TextColumn::make('assignedTo.name')->label('Responsável'),
            TextColumn::make('due_at')->label('Próximo contacto')->dateTime(),
            TextColumn::make('meta.utm.utm_source')->label('Origem'),
        ])->recordActions([
            EditAction::make(),
            Action::make('note')->label('Registar contacto')->schema([Textarea::make('body')->label('Nota de contacto')->required()->maxLength(3000)])->action(fn (Task $record, array $data) => $record->comments()->create(['body' => $data['body'], 'user_id' => auth()->id(), 'is_internal' => true])),
            Action::make('history')->label('Histórico e anexos')->modalContent(fn (Task $record) => view('filament.advertising-history', ['task' => $record->load(['comments.user', 'attachments'])]))->modalSubmitAction(false),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAdvertisingOpportunities::route('/')];
    }
}
