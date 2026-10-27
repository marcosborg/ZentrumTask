<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Models\VehicleRevision;
use App\Services\VehicleRevisionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revisões';

    protected static ?string $modelLabel = 'revisão';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $resource = $pageClass::getResource();

        return auth()->check() && $resource::canAccess() && $resource::canView($ownerRecord) && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    protected function canManageRevisions(): bool
    {
        $resource = $this->getPageClass()::getResource();

        return auth()->check() && $resource::canAccess() && $resource::canEdit($this->getOwnerRecord());
    }

    protected function canCreate(): bool
    {
        return $this->canManageRevisions() && parent::canCreate();
    }

    protected function canEdit(Model $record): bool
    {
        return $this->canManageRevisions() && parent::canEdit($record);
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canManageRevisions() && parent::canDelete($record);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components($this->revisionFields());
    }

    /** @return array<\Filament\Schemas\Components\Component> */
    protected function revisionFields(): array
    {
        return [
            DatePicker::make('scheduled_at')->label('Data agendada')->native(false),
            DatePicker::make('completed_at')->label('Data realizada')->maxDate(today())->native(false),
            TextInput::make('odometer')->label('Quilómetros na realização')->integer()->minValue(0)->maxValue(4294967295),
            TextInput::make('next_odometer')->label('Quilómetros da próxima revisão')->helperText('Total do conta-quilómetros, por exemplo 120000.')
                ->integer()->minValue(1)->maxValue(4294967295)
                ->gt(fn (Get $get): ?string => filled($get('odometer')) ? 'odometer' : null),
            TextInput::make('interval_years')->label('Anos até à próxima revisão')->default(1)->integer()->minValue(1)->maxValue(100)
                ->helperText('Deixe vazio para controlar apenas por quilómetros.'),
            Textarea::make('notes')->label('Notas')->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(function (): string {
                $vehicle = $this->getOwnerRecord()->fresh();
                $summary = app(VehicleRevisionService::class)->summary($vehicle);
                if ($summary === null) {
                    return 'Registe uma revisão realizada ou agende a primeira revisão.';
                }
                $reading = $summary['reading'];
                $kilometres = $reading['value'] === null ? 'Quilometragem indisponível' : number_format($reading['value'], 0, ',', '.').' km ('.$reading['source'].($reading['date'] ? ', '.$reading['date'] : '').')';

                return $kilometres.' · '.($summary['reasons'] ? implode(' · ', $summary['reasons']) : 'Sem avisos de revisão');
            })
            ->columns([
                TextColumn::make('scheduled_at')->label('Agendada')->date('d/m/Y')->sortable(),
                TextColumn::make('completed_at')->label('Realizada')->date('d/m/Y')->sortable(),
                TextColumn::make('state')->label('Estado')->state(fn (VehicleRevision $record): string => $record->isRecorded() ? 'Registada' : ($record->scheduled_at ? 'Agendada' : 'Por preencher'))->badge(),
                TextColumn::make('odometer')->label('Km na realização')->numeric(decimalPlaces: 0),
                TextColumn::make('next_odometer')->label('Próxima revisão (km)')->numeric(decimalPlaces: 0),
                TextColumn::make('next_date')->label('Próxima revisão (data)')->state(fn (VehicleRevision $record): ?\Illuminate\Support\Carbon => app(VehicleRevisionService::class)->nextDate($record))->date('d/m/Y'),
                TextColumn::make('notes')->label('Notas')->limit(60)->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Registar revisão')->authorize(fn (): bool => $this->canCreate())->fillForm(['interval_years' => 1]),
                CreateAction::make('schedule')->label('Agendar revisão')->authorize(fn (): bool => $this->canCreate())->schema([
                    DatePicker::make('scheduled_at')->label('Data agendada')->native(false),
                    Textarea::make('notes')->label('Notas'),
                ]),
            ])
            ->recordActions([
                Action::make('complete')->label('Concluir')->visible(fn (VehicleRevision $record): bool => ! $record->isRecorded())
                    ->authorize(fn (VehicleRevision $record): bool => $this->canEdit($record))
                    ->schema($this->revisionFields())
                    ->fillForm(fn (VehicleRevision $record): array => [...$record->attributesToArray(), 'completed_at' => today()->toDateString(), 'interval_years' => $record->interval_years ?? 1])
                    ->action(function (VehicleRevision $record, array $data): void {
                        abort_unless($this->canEdit($record), 403);
                        $record->update($data);
                    }),
                EditAction::make()->authorize(fn (VehicleRevision $record): bool => $this->canEdit($record)),
                DeleteAction::make()->authorize(fn (VehicleRevision $record): bool => $this->canDelete($record)),
            ])
            ->defaultSort('id', 'desc');
    }
}
