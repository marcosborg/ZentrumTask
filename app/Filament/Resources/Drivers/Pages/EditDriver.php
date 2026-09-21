<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Models\DocumentTemplate;
use App\Services\DriverDepositService;
use App\Services\DriverDocumentTemplateRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('createDepositDebit')
                ->label('Debitar caucao')
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->modalHeading('Registar debito de caucao')
                ->modalSubmitActionLabel('Registar debito')
                ->modalDescription('Regista um debito sobre a caucao acumulada do motorista.')
                ->form([
                    DatePicker::make('occurred_at')
                        ->label('Data')
                        ->default(now()->toDateString())
                        ->required()
                        ->native(false),
                    TextInput::make('amount')
                        ->label('Valor do debito')
                        ->required()
                        ->placeholder('Ex.: 75,00'),
                    TextInput::make('description')
                        ->label('Descricao')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $amount = $this->parseLocalizedDecimal($data['amount'] ?? null);

                    if ($amount === null || $amount <= 0) {
                        Notification::make()
                            ->danger()
                            ->title('Preencha um valor valido')
                            ->send();

                        return;
                    }

                    $description = trim((string) ($data['description'] ?? ''));

                    if ($description === '') {
                        Notification::make()
                            ->danger()
                            ->title('Preencha uma descricao')
                            ->send();

                        return;
                    }

                    app(DriverDepositService::class)->createDebitForDriver($this->record, [
                        'occurred_at' => $data['occurred_at'] ?? now()->toDateString(),
                        'amount' => $amount,
                        'description' => $description,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Debito de caucao registado')
                        ->send();
                }),
            Action::make('viewDepositHistory')
                ->label('Historico caucao')
                ->icon('heroicon-o-banknotes')
                ->modalHeading('Historico de caucao')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(function () {
                    $service = app(DriverDepositService::class);

                    return view('filament.pages.partials.driver-deposit-history-list', [
                        'summary' => $service->summaryForDriver($this->record),
                        'history' => $service->historyForDriver($this->record),
                    ]);
                }),
            Action::make('generatePdf')
                ->label('Gerar documento PDF')
                ->icon('heroicon-o-document-text')
                ->form([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(
                            DocumentTemplate::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (DocumentTemplate $template): array => [
                                    $template->id => trim($template->name.' ('.$template->internal_name.')'),
                                ])
                                ->toArray()
                        )
                        ->getSearchResultsUsing(function (string $search): array {
                            return DocumentTemplate::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('internal_name', 'like', "%{$search}%")
                                ->orderBy('name')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (DocumentTemplate $template): array => [
                                    $template->id => trim($template->name.' ('.$template->internal_name.')'),
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function (string $value): ?string {
                            $template = DocumentTemplate::find($value);

                            if (! $template) {
                                return null;
                            }

                            return trim($template->name.' ('.$template->internal_name.')');
                        })
                        ->helperText('Pesquise pelo nome interno ou nome do template.')
                        ->searchable()
                        ->required(),
                    TextInput::make('file_name')
                        ->label('Nome do ficheiro')
                        ->default(fn (): string => 'documento-driver-'.$this->record->id.'.pdf')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $template = DocumentTemplate::find($data['template_id']);

                    if (! $template) {
                        $this->notify('danger', 'Template nao encontrado.');

                        return;
                    }

                    $html = app(DriverDocumentTemplateRenderer::class)->render($template, $this->record);

                    $pdf = Pdf::loadHTML($html)
                        ->setPaper('a4')
                        ->setOption('isRemoteEnabled', true);

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $data['file_name'] ?: 'documento-driver-'.$this->record->id.'.pdf'
                    );
                }),
        ];
    }

    private function parseLocalizedDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.\-]/', '', $normalized) ?? '';

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
        }

        $normalized = str_replace(',', '.', $normalized);

        return round((float) $normalized, 2);
    }
}
