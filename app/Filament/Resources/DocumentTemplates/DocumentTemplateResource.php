<?php

namespace App\Filament\Resources\DocumentTemplates;

use App\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\EditDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Models\DocumentTemplate;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static UnitEnum|string|null $navigationGroup = 'Administracao';

    protected static ?int $navigationSort = 80;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template')
                    ->columns(12)
                    ->components([
                        TextInput::make('internal_name')
                            ->label('Nome interno')
                            ->helperText('Usado para procurar e gerar o PDF.')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->columnSpan(4),
                        TextInput::make('name')
                            ->label('Nome')
                            ->maxLength(255)
                            ->required()
                            ->columnSpan(8),
                        RichEditor::make('content')
                            ->label('Conteudo (use {{nome_do_campo}})')
                            ->columnSpan(8)
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'link',
                                'codeBlock',
                            ])
                            ->required(),
                        Section::make('Tokens disponiveis')
                            ->columnSpan(4)
                            ->components([
                                Html::make('tokens_list')
                                    ->content(static function (): string {
                                        $tokens = [
                                            'name',
                                            'email',
                                            'phone',
                                            'nif',
                                            'iban',
                                            'license_number',
                                            'notes',
                                            'date_of_birth',
                                            'nationality',
                                            'marital_status',
                                            'address',
                                            'identity_document_type',
                                            'identity_document_number',
                                            'identity_document_expires_at',
                                            'emergency_contact_name',
                                            'emergency_contact_phone',
                                            'sns_number',
                                            'niss_number',
                                            'license_issued_at',
                                            'license_expires_at',
                                            'license_category',
                                            'tvde_certificate_number',
                                            'tvde_certificate_expires_at',
                                            'tvde_platforms',
                                            'bank_account_holder',
                                            'deposit_amount',
                                            'deposit_paid_at',
                                            'deposit_payment_method',
                                            'candidate_application_id',
                                            'company.name',
                                            'company.email',
                                            'company.phone',
                                            'company.nif',
                                            'company.address',
                                            'company.city',
                                            'company.postal_code',
                                            'company.country',
                                            'company.iban',
                                            'id',
                                            'candidate_application.full_name',
                                            'candidate_application.email',
                                            'candidate_application.phone',
                                            'candidate_application.nif',
                                            'candidate_application.iban',
                                            'candidate_application.experience',
                                            'candidate_application.platforms',
                                            'vehicle.license_plate',
                                            'vehicle.vin',
                                            'vehicle.make',
                                            'vehicle.model',
                                            'vehicle.trim',
                                            'vehicle.year',
                                            'vehicle.fuel_type',
                                            'vehicle.transmission',
                                            'vehicle.color',
                                            'vehicle.seats',
                                            'vehicle.engine_cc',
                                            'vehicle.power_kw',
                                            'vehicle.current_odometer',
                                            'vehicle.status',
                                            'vehicle.acquisition_date',
                                            'vehicle.acquisition_cost',
                                            'vehicle.notes',
                                            'vehicle_allocation.starts_at',
                                            'vehicle_allocation.ends_at',
                                            'vehicle_allocation.start_odometer',
                                            'vehicle_allocation.end_odometer',
                                            'vehicle_allocation.status',
                                            'vehicle_allocation.handover_location',
                                            'vehicle_allocation.notes',
                                        ];

                                        $items = collect($tokens)
                                            ->map(function (string $token): string {
                                                $display = '{{'.$token.'}}';

                                                return '<div data-token="'.$display.'" style="cursor:pointer;padding:2px 0;"><code>'.$display.'</code></div>';
                                            })
                                            ->implode('');

                                        $script = <<<'HTML'
<script>
(function() {
    const feedback = document.getElementById('token-copy-feedback');
    document.querySelectorAll('[data-token]').forEach(function(el) {
        el.addEventListener('click', function() {
            const text = el.getAttribute('data-token');
            const done = function(msg) {
                if (feedback) {
                    feedback.textContent = msg;
                    feedback.style.display = 'block';
                    setTimeout(function() { feedback.style.display = 'none'; }, 1200);
                }
                el.style.color = '#2563eb';
                setTimeout(function() { el.style.color = ''; }, 600);
            };

            if (navigator?.clipboard?.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    done('Token copiado: ' + text);
                }).catch(function() {
                    done('Falha ao copiar');
                });
            } else {
                try {
                    const t = document.createElement('textarea');
                    t.value = text;
                    document.body.appendChild(t);
                    t.select();
                    document.execCommand('copy');
                    document.body.removeChild(t);
                    done('Token copiado: ' + text);
                } catch (e) {
                    done('Falha ao copiar');
                }
            }
        });
    });
})();
</script>
HTML;

                                        return '<div class="text-sm leading-5 text-gray-600 dark:text-gray-300">Copie e cole os tokens abaixo no conteudo. Campos de candidatura aninhada podem ser usados como {{candidate_application.full_name}} ou {{candidateApplication.full_name}} se existir a ligacao. Campos de empresa usam {{company.campo}}.</div><div class="mt-2 space-y-1">'.$items.'<div id="token-copy-feedback" class="text-xs text-emerald-600 dark:text-emerald-400 mt-2" style="display:none;"></div></div>'.$script;
                                    }),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('internal_name')
                    ->label('Nome interno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->sortable(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTemplates::route('/'),
            'create' => CreateDocumentTemplate::route('/create'),
            'edit' => EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
