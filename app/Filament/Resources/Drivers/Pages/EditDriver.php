<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Models\DocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
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

                    $html = $this->renderTemplate($template);

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

    private function renderTemplate(DocumentTemplate $template): string
    {
        $driver = $this->record->loadMissing('company', 'candidateApplication');
        $data = $driver->toArray();

        $driverDateFields = [
            'date_of_birth',
            'identity_document_expires_at',
            'license_issued_at',
            'license_expires_at',
            'tvde_certificate_expires_at',
            'deposit_paid_at',
            'created_at',
            'updated_at',
        ];

        foreach ($driverDateFields as $field) {
            $data[$field] = $this->formatDate($driver->{$field});
        }

        $candidateData = $driver->candidateApplication?->toArray() ?? [];

        $candidateDateFields = [
            'submitted_at',
            'last_saved_at',
            'rental_terms_accepted_at',
            'legal_confirmed_at',
        ];

        foreach ($candidateDateFields as $field) {
            $candidateData[$field] = $this->formatDate($driver->candidateApplication?->{$field});
        }

        $data['candidate_application'] = $candidateData;
        $data['candidateApplication'] = $candidateData;
        $content = $template->content;

        $rendered = preg_replace_callback('/{{\s*([\w\.]+)\s*}}/', function (array $matches) use ($data): string {
            $key = $matches[1];
            $value = data_get($data, $key, '');

            return e((string) $value);
        }, $content);

        $title = e($template->name);

        return <<<HTML
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Times New Roman", serif; color: #000; margin: 20px; line-height: 1.35; font-size: 13px; }
        h1 { margin: 0 0 14px; font-weight: bold; font-size: 16px; }
        h2 { margin: 0 0 12px; font-weight: bold; font-size: 14px; }
        h3 { margin: 0 0 12px; font-weight: bold; font-size: 13px; }
        p { margin: 0 0 14px; }
        ul, ol { margin: 0 0 14px 20px; }
        li { margin: 0 0 8px; }
        .header { margin-bottom: 12px; }
        .header img { height: 42px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="https://zentrum-tvde.com/website/assets/logo.svg" alt="Zentrum TVDE">
    </div>
    <h1>{$title}</h1>
    {$rendered}
</body>
    </html>
HTML;
    }

    private function formatDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
