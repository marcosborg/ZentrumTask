<?php

namespace App\Filament\Pages;

use App\Models\BoltSyncRun;
use App\Services\BoltCsvImportService;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use UnitEnum;

class BoltCsvImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Bolt - Importacao CSV';

    protected static ?string $title = 'Importacao Bolt CSV';

    protected string $view = 'filament.pages.bolt-csv-import';

    public ?array $data = [];

    public ?BoltSyncRun $lastRun = null;

    #[Url]
    public ?int $syncRunId = null;

    public function mount(): void
    {
        $this->form->fill([
            'file' => null,
            'delimiter' => 'auto',
            'has_header' => true,
            'matchers' => ['email', 'id', 'name'],
            'columns' => [
                'driver_name' => 'motorista',
                'driver_email' => 'email',
                'driver_identifier' => 'identificador_do_motorista',
                'bolt_driver_uuid' => 'identificador_do_motorista',
                'bolt_individual_uuid' => 'identificador_individual',
                'gross_total' => 'ganhos_brutos_total',
                'gross_app' => 'ganhos_brutos_pagamentos_na_app',
                'gross_cash' => 'ganhos_brutos_pagamentos_em_dinheiro',
                'net_total' => 'ganhos_liquidos',
                'expected_payment' => 'pagamento_previsto',
                'cash_collected' => 'dinheiro_recebido',
                'tips' => 'gorjetas_dos_passageiros',
                'commissions' => 'comissoes',
                'total_fees' => 'total_de_taxas',
                'reservation_fees' => 'taxas_de_reserva',
                'other_fees' => 'outras_taxas',
                'passenger_refunds' => 'reembolsos_aos_passageiros',
                'expense_reimbursements' => 'reembolsos_de_despesas',
                'tolls' => 'portagens',
                'campaign_earnings' => 'ganhos_da_campanha',
                'vat_app' => 'iva_sobre_os_ganhos_brutos_pagamentos_na_app',
                'vat_cash' => 'iva_sobre_os_ganhos_brutos_pagamentos_em_dinheiro',
                'vat_cancellation' => 'iva_das_taxas_de_cancelamento',
                'vat_reservation' => 'iva_das_taxas_de_reserva',
                'currency' => 'moeda',
                'date' => 'data',
            ],
        ]);

        if ($this->syncRunId) {
            $this->lastRun = BoltSyncRun::query()->find($this->syncRunId);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Ficheiro CSV')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('file')
                            ->label('CSV')
                            ->disk('local')
                            ->directory('bolt-imports')
                            ->preserveFilenames()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->required()
                            ->columnSpan(2),
                        Select::make('delimiter')
                            ->label('Separador')
                            ->options([
                                'auto' => 'Detetar automaticamente',
                                ',' => ',',
                                ';' => ';',
                                "\t" => 'TAB',
                            ])
                            ->required()
                            ->native(false),
                        Select::make('has_header')
                            ->label('Tem cabecalho?')
                            ->options([
                                true => 'Sim',
                                false => 'Nao',
                            ])
                            ->required()
                            ->native(false),
                        CheckboxList::make('matchers')
                            ->label('Associar motorista por')
                            ->options([
                                'email' => 'Email',
                                'id' => 'ID',
                                'name' => 'Nome',
                            ])
                            ->columns(3)
                            ->required()
                            ->columnSpan(2),
                    ]),
                Section::make('Mapeamento de colunas')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('week_start')
                            ->label('Semana inicio (opcional)')
                            ->native(false),
                        DatePicker::make('week_end')
                            ->label('Semana fim (opcional)')
                            ->native(false),
                        TextInput::make('columns.driver_name')
                            ->label('Coluna nome motorista')
                            ->maxLength(100),
                        TextInput::make('columns.driver_email')
                            ->label('Coluna email motorista')
                            ->maxLength(100),
                        TextInput::make('columns.driver_identifier')
                            ->label('Coluna ID Bolt')
                            ->maxLength(100),
                        TextInput::make('columns.bolt_driver_uuid')
                            ->label('Coluna UUID Bolt')
                            ->maxLength(100),
                        TextInput::make('columns.bolt_individual_uuid')
                            ->label('Coluna UUID individual')
                            ->maxLength(100),
                        TextInput::make('columns.gross_total')
                            ->label('Coluna ganhos brutos (total)')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('columns.gross_app')
                            ->label('Coluna ganhos brutos (app)')
                            ->maxLength(100),
                        TextInput::make('columns.gross_cash')
                            ->label('Coluna ganhos brutos (dinheiro)')
                            ->maxLength(100),
                        TextInput::make('columns.net_total')
                            ->label('Coluna ganhos liquidos')
                            ->maxLength(100),
                        TextInput::make('columns.expected_payment')
                            ->label('Coluna pagamento previsto')
                            ->maxLength(100),
                        TextInput::make('columns.cash_collected')
                            ->label('Coluna dinheiro recebido')
                            ->maxLength(100),
                        TextInput::make('columns.tips')
                            ->label('Coluna gorjetas')
                            ->maxLength(100),
                        TextInput::make('columns.commissions')
                            ->label('Coluna comissoes')
                            ->maxLength(100),
                        TextInput::make('columns.total_fees')
                            ->label('Coluna total de taxas')
                            ->maxLength(100),
                        TextInput::make('columns.reservation_fees')
                            ->label('Coluna taxas de reserva')
                            ->maxLength(100),
                        TextInput::make('columns.other_fees')
                            ->label('Coluna outras taxas')
                            ->maxLength(100),
                        TextInput::make('columns.passenger_refunds')
                            ->label('Coluna reembolsos aos passageiros')
                            ->maxLength(100),
                        TextInput::make('columns.expense_reimbursements')
                            ->label('Coluna reembolsos de despesas')
                            ->maxLength(100),
                        TextInput::make('columns.tolls')
                            ->label('Coluna portagens')
                            ->maxLength(100),
                        TextInput::make('columns.campaign_earnings')
                            ->label('Coluna ganhos da campanha')
                            ->maxLength(100),
                        TextInput::make('columns.vat_app')
                            ->label('Coluna IVA app')
                            ->maxLength(100),
                        TextInput::make('columns.vat_cash')
                            ->label('Coluna IVA dinheiro')
                            ->maxLength(100),
                        TextInput::make('columns.vat_cancellation')
                            ->label('Coluna IVA cancelamento')
                            ->maxLength(100),
                        TextInput::make('columns.vat_reservation')
                            ->label('Coluna IVA reserva')
                            ->maxLength(100),
                        TextInput::make('columns.currency')
                            ->label('Coluna moeda')
                            ->maxLength(100),
                        TextInput::make('columns.date')
                            ->label('Coluna data (ou calc semana)')
                            ->maxLength(100),
                    ]),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $data = $this->form->getState();
        $file = $data['file'] ?? null;

        if (! $file) {
            Notification::make()
                ->danger()
                ->title('CSV em falta')
                ->send();

            return;
        }

        $path = Storage::disk('local')->path($file);

        $options = [
            'columns' => $data['columns'] ?? [],
            'matchers' => $data['matchers'] ?? ['email', 'id', 'name'],
            'has_header' => (bool) ($data['has_header'] ?? true),
        ];

        if (($data['delimiter'] ?? 'auto') !== 'auto') {
            $options['delimiter'] = $data['delimiter'];
        }

        if (! empty($data['week_start'])) {
            $options['week_start'] = $data['week_start'];
        }

        if (! empty($data['week_end'])) {
            $options['week_end'] = $data['week_end'];
        }

        try {
            $syncRun = app(BoltCsvImportService::class)->import($path, $options);

            $this->lastRun = $syncRun;
            $this->syncRunId = $syncRun->id;

            Notification::make()
                ->success()
                ->title('Importacao concluida')
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Falha na importacao')
                ->body($exception->getMessage())
                ->send();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastRunSummary(): ?array
    {
        if (! $this->lastRun) {
            return null;
        }

        return [
            'status' => $this->lastRun->status,
            'rows' => $this->lastRun->row_count,
            'amount' => data_get($this->lastRun->totals, 'amount', 0),
            'drivers' => data_get($this->lastRun->totals, 'drivers', 0),
            'started_at' => $this->lastRun->started_at?->format('Y-m-d H:i'),
            'finished_at' => $this->lastRun->finished_at?->format('Y-m-d H:i'),
        ];
    }
}
