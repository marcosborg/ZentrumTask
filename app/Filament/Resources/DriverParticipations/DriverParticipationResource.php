<?php

namespace App\Filament\Resources\DriverParticipations;

use App\Enums\TvdeOperation;
use App\Filament\Resources\DriverParticipations\Pages\ManageDriverParticipations;
use App\Filament\Resources\SlotResource;
use App\Models\DriverAdjustment;
use App\Models\DriverParticipation;
use App\Models\SlotPack;
use App\Services\ParticipationService;
use App\Support\SlotForms;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriverParticipationResource extends SlotResource
{
    protected static ?string $model = DriverParticipation::class;

    protected static ?string $navigationLabel = 'Motoristas SLOT';

    protected static ?string $modelLabel = 'participação SLOT';

    protected static ?string $pluralModelLabel = 'Motoristas SLOT';

    public static function operation(): TvdeOperation
    {
        return TvdeOperation::Slot;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forOperation(static::operation())->with(['driver', 'vehicle', 'balance']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Hidden::make('operation')->default(static::operation()->value),
            Hidden::make('status')->default('preparing'),
            Select::make('driver_id')->label('Motorista')->relationship('driver', 'name')->searchable()->preload()->required()->disabled(fn (?DriverParticipation $record) => $record !== null)
                ->createOptionForm([
                    TextInput::make('name')->label('Nome')->required(),
                    TextInput::make('email')->email()->required(),
                    TextInput::make('phone')->label('Telefone'),
                    TextInput::make('nif')->label('NIF'),
                    TextInput::make('iban')->label('IBAN'),
                    Hidden::make('registration_operation')->default('preparing'),
                ]),
            Select::make('vehicle_id')->label('Viatura')->relationship('vehicle', 'license_plate', fn (Builder $query) => $query->forOperation(static::operation()))->searchable()->required()->disabled(fn (?DriverParticipation $record) => $record && $record->status !== 'preparing'),
            DatePicker::make('starts_at')->label('Entrada')->required()->disabled(fn (?DriverParticipation $record) => $record && $record->status !== 'preparing'),
            FileUpload::make('contract_file')->label('Contrato assinado')->disk('local')->directory('participation-contracts')->visibility('private')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
            Checkbox::make('documents_approved')->label('Documentação verificada')->dehydrated(false)->afterStateHydrated(fn ($component, ?DriverParticipation $record) => $component->state((bool) $record?->documents_approved_at)),
            TextInput::make('deposit_initial_amount')->label('Caução acordada')->numeric()->minValue(0)->default(0),
            TextInput::make('deposit_amount')->label('Caução paga')->numeric()->minValue(0)->default(0),
            DatePicker::make('deposit_paid_at')->label('Data da caução'),
            TextInput::make('deposit_payment_method')->label('Método de pagamento'),
            Textarea::make('notes')->label('Notas')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('driver.name')->label('Motorista')->searchable(),
            TextColumn::make('vehicle.license_plate')->label('Viatura'),
            TextColumn::make('status')->label('Estado')->state(fn (DriverParticipation $record) => $record->currentStatus())->badge()->formatStateUsing(fn ($state) => ['preparing' => 'Preparação', 'scheduled' => 'Agendada', 'suspended' => 'Suspensa', 'active' => 'Ativa', 'closed' => 'Encerrada'][$state] ?? $state),
            TextColumn::make('starts_at')->label('Entrada')->date('d/m/Y'),
            TextColumn::make('ends_at')->label('Fim')->date('d/m/Y'),
            TextColumn::make('balance.current_balance')->label('Saldo')->money('EUR'),
            TextColumn::make('deposit_amount')->label('Caução paga')->money('EUR'),
            TextColumn::make('pack')->label('Pack')->state(fn (DriverParticipation $record) => app(ParticipationService::class)->packFor($record, now(config('slots.timezone'))->toDateString())?->name),
        ])->recordActions([
            EditAction::make()->mutateDataUsing(function (array $data): array {
                unset($data['status']);
                $data['operation'] = static::operation()->value;

                return $data;
            }),
            Action::make('identity')->label('Dados do motorista')->schema([
                TextInput::make('name')->label('Nome')->required(),
                TextInput::make('email')->email()->required(),
                TextInput::make('phone')->label('Telefone'),
                TextInput::make('nif')->label('NIF'),
                TextInput::make('iban')->label('IBAN'),
                TextInput::make('uber_driver_code')->label('Código Uber'),
                TextInput::make('bolt_driver_code')->label('Código Bolt'),
                TextInput::make('tvde_certificate_number')->label('Certificado TVDE'),
                DatePicker::make('tvde_certificate_expires_at')->label('Validade TVDE'),
            ])->fillForm(fn (DriverParticipation $record) => $record->driver->only(['name', 'email', 'phone', 'nif', 'iban', 'uber_driver_code', 'bolt_driver_code', 'tvde_certificate_number', 'tvde_certificate_expires_at']))
                ->action(fn (DriverParticipation $record, array $data) => $record->driver->update($data)),
            Action::make('approveDocuments')->label('Validar documentos')->requiresConfirmation()->action(fn (DriverParticipation $record) => $record->update(['documents_approved_at' => now(), 'documents_approved_by' => auth()->id()])),
            Action::make('fiscal')->label('Perfil fiscal')->visible(static::operation() === TvdeOperation::Slot)->schema(SlotForms::fiscal())->action(function (DriverParticipation $record, array $data): void {
                app(ParticipationService::class)->setFiscalProfile($record, $data);
            }),
            Action::make('pack')->label('Atribuir / mudar pack')->visible(static::operation() === TvdeOperation::Slot)->schema([
                Select::make('slot_pack_id')->label('Pack')->options(SlotPack::query()->orderByDesc('valid_from')->get()->mapWithKeys(fn ($pack) => [$pack->id => $pack->name.' - '.$pack->weekly_price.' € ('.$pack->valid_from->format('d/m/Y').')']))->required(),
                DatePicker::make('starts_at')->label('Data de início')->required(),
            ])->action(fn (DriverParticipation $record, array $data) => app(ParticipationService::class)->assignPack($record, SlotPack::query()->findOrFail($data['slot_pack_id']), $data['starts_at'])),
            Action::make('activate')->label('Ativar')->visible(fn (DriverParticipation $record) => $record->status === 'preparing')->action(fn (DriverParticipation $record) => app(ParticipationService::class)->activate($record)),
            Action::make('suspend')->label('Suspender')->visible(fn (DriverParticipation $record) => $record->status !== 'preparing')->schema([
                DatePicker::make('starts_at')->label('Início')->required(),
                DatePicker::make('ends_at')->label('Fim')->required()->afterOrEqual('starts_at'),
                Textarea::make('reason')->label('Motivo')->required(),
            ])->action(fn (DriverParticipation $record, array $data) => app(ParticipationService::class)->suspend($record, $data['starts_at'], $data['ends_at'], $data['reason'])),
            Action::make('transition')->label('Transitar operação')->visible(fn (DriverParticipation $record) => ! $record->ends_at && $record->status !== 'preparing')->schema([
                Select::make('target_id')->label('Participação de destino em preparação')->options(fn (DriverParticipation $record) => DriverParticipation::query()->where('driver_id', $record->driver_id)->where('operation', '!=', $record->operation->value)->where('status', 'preparing')->get()->mapWithKeys(fn ($row) => [$row->id => $row->operation->label().' / '.$row->starts_at->format('d/m/Y')]))->required(),
            ])->action(fn (DriverParticipation $record, array $data) => app(ParticipationService::class)->transition($record, DriverParticipation::query()->findOrFail($data['target_id']))),
            Action::make('close')->label('Encerrar')->visible(fn (DriverParticipation $record) => ! $record->ends_at && $record->status !== 'preparing')->schema([DatePicker::make('ends_at')->label('Fim (primeiro dia fora da operação)')->required()])->action(function (DriverParticipation $record, array $data): void {
                app(ParticipationService::class)->close($record, $data['ends_at']);
            }),
            Action::make('adjustment')->label('Ajuste')->schema([
                DatePicker::make('starts_at')->label('Data de origem')->required(),
                TextInput::make('amount')->label('Valor (negativo para crédito)')->numeric()->required(),
                Textarea::make('description')->label('Descrição')->required(),
            ])->action(fn (DriverParticipation $record, array $data) => DriverAdjustment::query()->create($data + ['driver_id' => $record->driver_id, 'driver_participation_id' => $record->id, 'operation' => $record->operation->value])),
            Action::make('regularize')->label('Regularizar saldo')->schema([
                TextInput::make('amount')->label('Valor (positivo para recebimento do motorista)')->numeric()->required(),
                Textarea::make('description')->label('Descrição / comprovativo')->required(),
            ])->action(fn (DriverParticipation $record, array $data) => app(ParticipationService::class)->regularize($record, (float) $data['amount'], $data['description'])),
        ])->defaultSort('starts_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDriverParticipations::route('/')];
    }
}
