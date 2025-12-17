<?php

namespace App\Filament\Resources\CandidateApplications;

use App\Filament\Resources\CandidateApplications\Pages\EditCandidateApplication;
use App\Filament\Resources\CandidateApplications\Pages\ListCandidateApplications;
use App\Filament\Resources\CandidateApplications\Pages\ViewCandidateApplication;
use App\Filament\Resources\CandidateApplications\Tables\CandidateApplicationsTable;
use App\Models\CandidateApplication;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CandidateApplicationResource extends Resource
{
    protected static ?string $model = CandidateApplication::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estado')
                    ->columns(3)
                    ->components([
                        Select::make('status')
                            ->options([
                                'draft' => 'Rascunho',
                                'incomplete' => 'Incompleta',
                                'submitted' => 'Submetida',
                            ])
                            ->required(),
                        TextInput::make('current_step')
                            ->label('Passo atual')
                            ->maxLength(50),
                        TextInput::make('last_ip')
                            ->label('Ultimo IP')
                            ->maxLength(45),
                    ]),
                Section::make('Dados pessoais')
                    ->columns(2)
                    ->components([
                        TextInput::make('full_name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telemovel')
                            ->maxLength(30),
                        TextInput::make('nif')
                            ->maxLength(30),
                    ]),
                Section::make('Elegibilidade')
                    ->columns(2)
                    ->components([
                        Toggle::make('has_tvde_course')
                            ->label('Tem curso TVDE?'),
                        Toggle::make('certificate_valid')
                            ->label('Certificado valido?'),
                        TextInput::make('experience')
                            ->label('Experiencia')
                            ->maxLength(255),
                        TagsInput::make('platforms')
                            ->label('Plataformas'),
                    ]),
                Section::make('Documentos')
                    ->columns(2)
                    ->components([
                        FileUpload::make('documents.document_id')
                            ->label('Documento de identificacao')
                            ->disk('public')
                            ->directory(fn (?CandidateApplication $record): string => $record ? "applications/{$record->token}" : 'applications')
                            ->visibility('public')
                            ->downloadable()
                            ->formatStateUsing(fn ($state): ?string => self::documentPath($state))
                            ->dehydrateStateUsing(fn ($state): ?array => self::dehydrateDocument($state)),
                        FileUpload::make('documents.driver_license')
                            ->label('Carta de conducao')
                            ->disk('public')
                            ->directory(fn (?CandidateApplication $record): string => $record ? "applications/{$record->token}" : 'applications')
                            ->visibility('public')
                            ->downloadable()
                            ->formatStateUsing(fn ($state): ?string => self::documentPath($state))
                            ->dehydrateStateUsing(fn ($state): ?array => self::dehydrateDocument($state)),
                        FileUpload::make('documents.tvde_certificate')
                            ->label('Certificado TVDE')
                            ->disk('public')
                            ->directory(fn (?CandidateApplication $record): string => $record ? "applications/{$record->token}" : 'applications')
                            ->visibility('public')
                            ->downloadable()
                            ->formatStateUsing(fn ($state): ?string => self::documentPath($state))
                            ->dehydrateStateUsing(fn ($state): ?array => self::dehydrateDocument($state)),
                        FileUpload::make('documents.criminal_record')
                            ->label('Registo criminal')
                            ->disk('public')
                            ->directory(fn (?CandidateApplication $record): string => $record ? "applications/{$record->token}" : 'applications')
                            ->visibility('public')
                            ->downloadable()
                            ->formatStateUsing(fn ($state): ?string => self::documentPath($state))
                            ->dehydrateStateUsing(fn ($state): ?array => self::dehydrateDocument($state)),
                    ]),
                Section::make('Confirmacoes')
                    ->columns(3)
                    ->components([
                        Toggle::make('rental_terms_accept')
                            ->label('Aceitou condicoes'),
                        Toggle::make('rgpd')
                            ->label('RGPD'),
                        Toggle::make('truth_declaration')
                            ->label('Declaracao de veracidade'),
                        Toggle::make('contact_authorization')
                            ->label('Autorizou contacto'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CandidateApplicationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estado')
                    ->columns(3)
                    ->components([
                        Text::make('status_label', 'Estado')
                            ->state(fn (CandidateApplication $record): string => match ($record->status) {
                                'submitted' => 'Submetida',
                                'incomplete' => 'Incompleta',
                                default => 'Rascunho',
                            })
                            ->color(fn (CandidateApplication $record): string => match ($record->status) {
                                'submitted' => 'success',
                                'incomplete' => 'warning',
                                default => 'gray',
                            })
                            ->weight('semibold'),
                        Text::make('current_step_label', 'Passo atual')
                            ->state(fn (CandidateApplication $record): string => (string) ($record->current_step ?? '-')),
                        Text::make('ip_label', 'IP')
                            ->state(fn (CandidateApplication $record): string => (string) ($record->last_ip ?? '-')),
                        Text::make('created_label', 'Criada em')
                            ->state(fn (CandidateApplication $record): string => optional($record->created_at)->format('Y-m-d H:i') ?? '-'),
                        Text::make('submitted_label', 'Submetida em')
                            ->state(fn (CandidateApplication $record): string => optional($record->submitted_at)->format('Y-m-d H:i') ?? '-'),
                        Text::make('last_saved_label', 'Ultimo guardar')
                            ->state(fn (CandidateApplication $record): string => optional($record->last_saved_at)->format('Y-m-d H:i') ?? '-'),
                    ]),
                Section::make('Dados pessoais')
                    ->columns(2)
                    ->components([
                        Text::make('full_name_label', 'Nome')
                            ->state(fn (CandidateApplication $record): string => (string) ($record->full_name ?? '-')),
                        Text::make('email_label', 'Email')
                            ->state(fn (CandidateApplication $record): string => (string) ($record->email ?? '-')),
                        Text::make('phone_label', 'Telemovel')
                            ->state(fn (CandidateApplication $record): string => (string) ($record->phone ?? '-')),
                        Text::make('nif_label', 'NIF')
                            ->state(fn (CandidateApplication $record): string => (string) ($record->nif ?? '-')),
                    ]),
                Section::make('Elegibilidade')
                    ->columns(2)
                    ->components([
                        Text::make('has_tvde_course_label', 'Tem curso TVDE?')
                            ->state(fn (CandidateApplication $record): string => $record->has_tvde_course ? 'Sim' : 'Nao'),
                        Text::make('certificate_valid_label', 'Certificado valido?')
                            ->state(fn (CandidateApplication $record): string => $record->certificate_valid ? 'Sim' : 'Nao'),
                        Text::make('experience_label', 'Experiencia anterior')
                            ->state(fn (CandidateApplication $record): string => (string) ($record->experience ?? '-')),
                        Text::make('platforms_label', 'Plataformas')
                            ->state(fn (CandidateApplication $record): string => ($record->platforms && is_array($record->platforms)) ? implode(', ', $record->platforms) : '-'),
                    ]),
                Section::make('Confirmacoes')
                    ->columns(3)
                    ->components([
                        Text::make('rental_terms_accept_label', 'Aceitou condicoes')
                            ->state(fn (CandidateApplication $record): string => $record->rental_terms_accept ? 'Sim' : 'Nao'),
                        Text::make('rgpd_label', 'RGPD')
                            ->state(fn (CandidateApplication $record): string => $record->rgpd ? 'Sim' : 'Nao'),
                        Text::make('truth_declaration_label', 'Declaracao de veracidade')
                            ->state(fn (CandidateApplication $record): string => $record->truth_declaration ? 'Sim' : 'Nao'),
                        Text::make('contact_authorization_label', 'Autorizou contacto')
                            ->state(fn (CandidateApplication $record): string => $record->contact_authorization ? 'Sim' : 'Nao'),
                    ]),
                Section::make('Documentos')
                    ->components([
                        Grid::make(2)->components([
                            Text::make('document_id_label', 'Documento de identificacao')
                                ->state(fn (CandidateApplication $record): string => self::documentName($record, 'document_id')),
                            Text::make('driver_license_label', 'Carta de conducao')
                                ->state(fn (CandidateApplication $record): string => self::documentName($record, 'driver_license')),
                            Text::make('tvde_certificate_label', 'Certificado TVDE')
                                ->state(fn (CandidateApplication $record): string => self::documentName($record, 'tvde_certificate')),
                            Text::make('criminal_record_label', 'Registo criminal')
                                ->state(fn (CandidateApplication $record): string => self::documentName($record, 'criminal_record')),
                        ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateApplications::route('/'),
            'view' => ViewCandidateApplication::route('/{record}'),
            'edit' => EditCandidateApplication::route('/{record}/edit'),
        ];
    }

    private static function documentName(CandidateApplication $record, string $key): string
    {
        $value = $record->documents[$key] ?? null;

        if (is_array($value)) {
            return $value['name'] ?? basename((string) ($value['path'] ?? '')) ?: '-';
        }

        if (is_string($value) && $value !== '') {
            return basename($value);
        }

        return '-';
    }

    private static function documentPath(mixed $state): ?string
    {
        if (is_array($state)) {
            return $state['path'] ?? null;
        }

        return $state ?: null;
    }

    private static function dehydrateDocument(mixed $state): ?array
    {
        if ($state === null || $state === '') {
            return null;
        }

        $path = is_array($state) ? ($state['path'] ?? null) : (string) $state;

        if ($path === null || $path === '') {
            return null;
        }

        return [
            'path' => $path,
            'name' => basename($path),
        ];
    }
}
