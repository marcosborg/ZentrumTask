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
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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
                                'converted' => 'Convertida',
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
                        TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(34),
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
                            ->fetchFileInformation(false)
                            ->multiple()
                            ->reorderable()
                            ->downloadable()
                            ->afterStateHydrated(function (FileUpload $component, $state, ?CandidateApplication $record): void {
                                $component->state(self::documentPaths($record, $state));
                            })
                            ->dehydrateStateUsing(fn ($state): array => self::dehydrateDocumentList($state)),
                        FileUpload::make('documents.driver_license')
                            ->label('Carta de conducao')
                            ->disk('public')
                            ->directory(fn (?CandidateApplication $record): string => $record ? "applications/{$record->token}" : 'applications')
                            ->visibility('public')
                            ->fetchFileInformation(false)
                            ->multiple()
                            ->reorderable()
                            ->downloadable()
                            ->afterStateHydrated(function (FileUpload $component, $state, ?CandidateApplication $record): void {
                                $component->state(self::documentPaths($record, $state));
                            })
                            ->dehydrateStateUsing(fn ($state): array => self::dehydrateDocumentList($state)),
                        FileUpload::make('documents.tvde_certificate')
                            ->label('Certificado TVDE')
                            ->disk('public')
                            ->directory(fn (?CandidateApplication $record): string => $record ? "applications/{$record->token}" : 'applications')
                            ->visibility('public')
                            ->fetchFileInformation(false)
                            ->multiple()
                            ->reorderable()
                            ->downloadable()
                            ->afterStateHydrated(function (FileUpload $component, $state, ?CandidateApplication $record): void {
                                $component->state(self::documentPaths($record, $state));
                            })
                            ->dehydrateStateUsing(fn ($state): array => self::dehydrateDocumentList($state)),
                        FileUpload::make('documents.criminal_record')
                            ->label('Registo criminal')
                            ->disk('public')
                            ->directory(fn (?CandidateApplication $record): string => $record ? "applications/{$record->token}" : 'applications')
                            ->visibility('public')
                            ->fetchFileInformation(false)
                            ->multiple()
                            ->reorderable()
                            ->downloadable()
                            ->afterStateHydrated(function (FileUpload $component, $state, ?CandidateApplication $record): void {
                                $component->state(self::documentPaths($record, $state));
                            })
                            ->dehydrateStateUsing(fn ($state): array => self::dehydrateDocumentList($state)),
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
                        Text::make(fn (CandidateApplication $record): string => 'Estado: '.match ($record->status) {
                            'submitted' => 'Submetida',
                            'incomplete' => 'Incompleta',
                            'converted' => 'Convertida',
                            default => 'Rascunho',
                        })
                            ->color(fn (CandidateApplication $record): string => match ($record->status) {
                                'submitted' => 'success',
                                'incomplete' => 'warning',
                                'converted' => 'info',
                                default => 'gray',
                            })
                            ->weight('semibold'),
                        Text::make(fn (CandidateApplication $record): string => 'Passo atual: '.($record->current_step ?? '-')),
                        Text::make(fn (CandidateApplication $record): string => 'IP: '.($record->last_ip ?? '-')),
                        Text::make(fn (CandidateApplication $record): string => 'Criada em: '.(optional($record->created_at)->format('Y-m-d H:i') ?? '-')),
                        Text::make(fn (CandidateApplication $record): string => 'Submetida em: '.(optional($record->submitted_at)->format('Y-m-d H:i') ?? '-')),
                        Text::make(fn (CandidateApplication $record): string => 'Ultimo guardar: '.(optional($record->last_saved_at)->format('Y-m-d H:i') ?? '-')),
                    ]),
                Section::make('Dados pessoais')
                    ->columns(2)
                    ->components([
                        Text::make(fn (CandidateApplication $record): string => 'Nome: '.((string) ($record->full_name ?? '-'))),
                        Text::make(fn (CandidateApplication $record): string => 'Email: '.((string) ($record->email ?? '-'))),
                        Text::make(fn (CandidateApplication $record): string => 'Telemovel: '.((string) ($record->phone ?? '-'))),
                        Text::make(fn (CandidateApplication $record): string => 'NIF: '.((string) ($record->nif ?? '-'))),
                        Text::make(fn (CandidateApplication $record): string => 'IBAN: '.((string) ($record->iban ?? '-'))),
                    ]),
                Section::make('Elegibilidade')
                    ->columns(2)
                    ->components([
                        Text::make(fn (CandidateApplication $record): string => 'Tem curso TVDE?: '.($record->has_tvde_course ? 'Sim' : 'Nao')),
                        Text::make(fn (CandidateApplication $record): string => 'Certificado valido?: '.($record->certificate_valid ? 'Sim' : 'Nao')),
                        Text::make(fn (CandidateApplication $record): string => 'Experiencia anterior: '.((string) ($record->experience ?? '-'))),
                        Text::make(fn (CandidateApplication $record): string => 'Plataformas: '.(($record->platforms && is_array($record->platforms)) ? implode(', ', $record->platforms) : '-')),
                    ]),
                Section::make('Confirmacoes')
                    ->columns(3)
                    ->components([
                        Text::make(fn (CandidateApplication $record): string => 'Aceitou condicoes: '.($record->rental_terms_accept ? 'Sim' : 'Nao')),
                        Text::make(fn (CandidateApplication $record): string => 'RGPD: '.($record->rgpd ? 'Sim' : 'Nao')),
                        Text::make(fn (CandidateApplication $record): string => 'Declaracao de veracidade: '.($record->truth_declaration ? 'Sim' : 'Nao')),
                        Text::make(fn (CandidateApplication $record): string => 'Autorizou contacto: '.($record->contact_authorization ? 'Sim' : 'Nao')),
                    ]),
                Section::make('Documentos')
                    ->components([
                        Grid::make(2)->components([
                            Html::make(fn (CandidateApplication $record): HtmlString => self::documentLink('Documento de identificacao', $record, 'document_id')),
                            Html::make(fn (CandidateApplication $record): HtmlString => self::documentLink('Carta de conducao', $record, 'driver_license')),
                            Html::make(fn (CandidateApplication $record): HtmlString => self::documentLink('Certificado TVDE', $record, 'tvde_certificate')),
                            Html::make(fn (CandidateApplication $record): HtmlString => self::documentLink('Registo criminal', $record, 'criminal_record')),
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
        $entries = self::normalizeDocumentItems($record, $record->documents[$key] ?? null);

        if ($entries === []) {
            return '-';
        }

        return $entries[0]['name'] ?? basename($entries[0]['path']);
    }

    private static function documentPath(?CandidateApplication $record, mixed $state): ?string
    {
        if (is_array($state)) {
            return self::resolveDocumentPath($record, $state['path'] ?? null);
        }

        return self::resolveDocumentPath($record, $state ?: null);
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

    /**
     * @return array<int, array{path: string, name: string}>
     */
    private static function dehydrateDocumentList(mixed $state): array
    {
        $paths = [];

        foreach ((array) $state as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $paths[] = [
                'path' => $value,
                'name' => basename($value),
            ];
        }

        return $paths;
    }

    private static function documentLink(string $label, CandidateApplication $record, string $key): HtmlString
    {
        $entries = self::normalizeDocumentItems($record, $record->documents[$key] ?? null);

        if ($entries === []) {
            return new HtmlString("{$label}: -");
        }

        $links = [];

        foreach ($entries as $entry) {
            $url = Storage::disk('public')->url($entry['path']);
            $name = $entry['name'] ?? basename($entry['path']);
            $links[] = '<a href="'.e($url).'" class="underline text-primary" target="_blank" rel="noopener">'.$name.'</a>';
        }

        return new HtmlString("{$label}: ".implode('<br>', $links));
    }

    private static function normalizeDocumentPath(CandidateApplication $record, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if (preg_match('#applications/.+$#', $path, $matches)) {
            return $matches[0];
        }

        if (! str_contains($path, '/')) {
            return "applications/{$record->token}/{$path}";
        }

        return "applications/{$record->token}/".basename($path);
    }

    public static function resolveDocumentPath(CandidateApplication $record, ?string $path): ?string
    {
        $normalized = self::normalizeDocumentPath($record, $path);

        if ($normalized === null) {
            return null;
        }

        if (Storage::disk('public')->exists($normalized)) {
            return $normalized;
        }

        $fallback = "applications/{$record->token}/".basename($normalized);

        if (Storage::disk('public')->exists($fallback)) {
            return $fallback;
        }

        return $normalized;
    }

    /**
     * @return array<int, array{path: string, name: string}>
     */
    public static function normalizeDocumentItems(CandidateApplication $record, mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = [];

        if (is_array($value)) {
            $candidates = array_is_list($value) ? $value : [$value];

            foreach ($candidates as $candidate) {
                if (is_array($candidate)) {
                    $path = $candidate['path'] ?? null;
                    if (! is_string($path) || $path === '') {
                        continue;
                    }

                    $resolved = self::resolveDocumentPath($record, $path);
                    if (! $resolved) {
                        continue;
                    }

                    $items[] = [
                        'path' => $resolved,
                        'name' => $candidate['name'] ?? basename($resolved),
                    ];

                    continue;
                }

                if (is_string($candidate) && $candidate !== '') {
                    $resolved = self::resolveDocumentPath($record, $candidate);
                    if (! $resolved) {
                        continue;
                    }

                    $items[] = [
                        'path' => $resolved,
                        'name' => basename($resolved),
                    ];
                }
            }

            return $items;
        }

        if (is_string($value)) {
            $resolved = self::resolveDocumentPath($record, $value);

            return $resolved
                ? [[
                    'path' => $resolved,
                    'name' => basename($resolved),
                ]]
                : [];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private static function documentPaths(?CandidateApplication $record, mixed $state): array
    {
        if (! $record) {
            return [];
        }

        $items = self::normalizeDocumentItems($record, $state);

        return array_values(array_map(fn (array $item): string => $item['path'], $items));
    }
}
