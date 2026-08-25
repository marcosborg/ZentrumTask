<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Drivers\Tables\DriversTable;
use App\Models\Driver;
use App\Services\DriverDepositService;
use BackedEnum;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identificacao')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nome')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefone')
                            ->maxLength(50),
                        TextInput::make('nif')
                            ->label('NIF')
                            ->maxLength(50),
                        DatePicker::make('date_of_birth')
                            ->label('Data de nascimento'),
                        TextInput::make('nationality')
                            ->label('Nacionalidade')
                            ->maxLength(255),
                        TextInput::make('marital_status')
                            ->label('Estado civil')
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label('Morada')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
                Section::make('Documentos do Contrato')
                    ->columns(2)
                    ->components([
                        self::driverDocumentUpload('contract_file')
                            ->label('Contrato digitalizado')
                            ->directory(fn ($record): string => $record ? "drivers/{$record->id}/contract" : 'drivers/contracts')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames(),
                        self::driverDocumentUpload('other_documents')
                            ->label('Outros documentos')
                            ->directory(fn ($record): string => $record ? "drivers/{$record->id}/documents" : 'drivers/documents')
                            ->multiple()
                            ->downloadable()
                            ->openable()
                            ->reorderable()
                            ->preserveFilenames()
                            ->columnSpan(2),
                    ]),
                Section::make('Empresa')
                    ->columns(2)
                    ->components([
                        Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->placeholder('Sem empresa')
                            ->native(false),
                    ]),
                Section::make('Documento de Identificacao')
                    ->columns(2)
                    ->components([
                        TextInput::make('identity_document_type')
                            ->label('Tipo de documento')
                            ->maxLength(255),
                        TextInput::make('identity_document_number')
                            ->label('Numero do documento')
                            ->maxLength(255),
                        DatePicker::make('identity_document_expires_at')
                            ->label('Validade do documento'),
                        TextInput::make('sns_number')
                            ->label('Numero SNS')
                            ->maxLength(50),
                        TextInput::make('niss_number')
                            ->label('Numero NISS')
                            ->maxLength(50),
                    ]),
                Section::make('Carta de Conducao')
                    ->columns(2)
                    ->components([
                        TextInput::make('license_number')
                            ->label('Numero da carta')
                            ->maxLength(100),
                        DatePicker::make('license_issued_at')
                            ->label('Emitida em'),
                        DatePicker::make('license_expires_at')
                            ->label('Validade'),
                        TextInput::make('license_category')
                            ->label('Categoria')
                            ->maxLength(255),
                    ]),
                Section::make('TVDE')
                    ->columns(2)
                    ->components([
                        TextInput::make('tvde_certificate_number')
                            ->label('Numero certificado TVDE')
                            ->maxLength(255),
                        DatePicker::make('tvde_certificate_expires_at')
                            ->label('Validade certificado'),
                        TextInput::make('bolt_driver_code')
                            ->label('Codigo Bolt')
                            ->maxLength(255),
                        TextInput::make('uber_driver_code')
                            ->label('Codigo Uber')
                            ->maxLength(255),
                        CheckboxList::make('tvde_platforms')
                            ->label('Plataformas')
                            ->options([
                                'uber' => 'Uber',
                                'bolt' => 'Bolt',
                                'free_now' => 'FREE NOW',
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Contacto de Emergencia')
                    ->columns(2)
                    ->components([
                        TextInput::make('emergency_contact_name')
                            ->label('Nome do contacto')
                            ->maxLength(255),
                        TextInput::make('emergency_contact_phone')
                            ->label('Telefone do contacto')
                            ->maxLength(255),
                    ]),
                Section::make('Dados Bancarios')
                    ->columns(2)
                    ->components([
                        TextInput::make('bank_account_holder')
                            ->label('Titular da conta')
                            ->maxLength(255),
                        TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(34),
                    ]),
                Section::make('Caucao')
                    ->columns(3)
                    ->components([
                        TextInput::make('deposit_initial_amount')
                            ->label('Valor acordado caucao')
                            ->numeric()
                            ->step('0.01')
                            ->prefix('€'),
                        TextInput::make('deposit_amount')
                            ->label('Valor pago no ato inicial')
                            ->numeric()
                            ->step('0.01')
                            ->prefix('€'),
                        DatePicker::make('deposit_paid_at')
                            ->label('Pago em'),
                        TextInput::make('deposit_payment_method')
                            ->label('Metodo de pagamento')
                            ->maxLength(255),
                        Placeholder::make('deposit_adjustments_total')
                            ->label('Ajustes caucao cobrados')
                            ->content(function (?Driver $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                $summary = app(DriverDepositService::class)->summaryForDriver($record);

                                return number_format((float) $summary['adjustments_total'], 2, ',', ' ').' €';
                            }),
                        Placeholder::make('deposit_debits_total')
                            ->label('Debitos caucao')
                            ->content(function (?Driver $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                $summary = app(DriverDepositService::class)->summaryForDriver($record);

                                return number_format((float) $summary['debits_total'], 2, ',', ' ').' €';
                            }),
                        Placeholder::make('deposit_balance')
                            ->label('Caucao acumulada')
                            ->content(function (?Driver $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                $summary = app(DriverDepositService::class)->summaryForDriver($record);

                                return number_format((float) $summary['current_balance'], 2, ',', ' ').' €';
                            }),
                    ]),
                Section::make('Candidatura')
                    ->columns(2)
                    ->components([
                        Select::make('candidate_application_id')
                            ->label('Candidatura')
                            ->relationship('candidateApplication', 'full_name')
                            ->searchable()
                            ->placeholder('Sem candidatura')
                            ->native(false),
                    ]),
            ]);
    }

    protected static function driverDocumentUpload(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->disk('public')
            ->visibility('private')
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(static fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): array => self::uploadedDriverDocument(
                $component,
                $file,
                $storedFileNames,
            ))
            ->previewable(false);
    }

    /**
     * @return array{name: string, size: int, type: null, url: string}
     */
    protected static function uploadedDriverDocument(BaseFileUpload $component, string $file, string|array|null $storedFileNames): array
    {
        return [
            'name' => ($component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames) ?? basename($file),
            'size' => 0,
            'type' => null,
            'url' => $component->getDisk()->url($file),
        ];
    }

    public static function table(Table $table): Table
    {
        return DriversTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BillingProfilesRelationManager::class,
            RelationManagers\VehicleAllocationsRelationManager::class,
            RelationManagers\WeekStatementsRelationManager::class,
            RelationManagers\CandidateApplicationRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
}
