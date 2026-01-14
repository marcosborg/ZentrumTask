<?php

namespace App\Filament\Resources\CandidateApplications\Pages;

use App\Filament\Resources\CandidateApplications\CandidateApplicationResource;
use App\Filament\Resources\Drivers\DriverResource;
use App\Models\Driver;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ViewCandidateApplication extends ViewRecord
{
    protected static string $resource = CandidateApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Descarregar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $record = $this->record;
                    $documents = $this->gatherDocuments();
                    $pdf = Pdf::loadView('pdf.candidate-application', [
                        'record' => $record,
                        'documents' => $documents,
                        'logo' => $this->logoDataUri(),
                    ])->setPaper('a4');
                    $pdf->getDomPDF()->set_option('enable_remote', true);

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        'candidatura-'.$record->id.'.pdf'
                    );
                }),
            Action::make('createDriver')
                ->label('Criar Driver')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => $this->record?->status === 'submitted')
                ->action(function (): void {
                    $driver = DB::transaction(function (): Driver {
                        $driver = Driver::create([
                            'name' => $this->record->full_name,
                            'email' => $this->record->email,
                            'phone' => $this->record->phone,
                            'nif' => $this->record->nif,
                            'iban' => $this->record->iban,
                            'candidate_application_id' => $this->record->id,
                            'notes' => 'Criado a partir da candidatura '.$this->record->id,
                        ]);

                        $this->record->update([
                            'status' => 'converted',
                        ]);

                        return $driver;
                    });

                    Notification::make()
                        ->success()
                        ->title('Driver criado')
                        ->body('Registo criado a partir da candidatura.')
                        ->send();

                    $this->redirect(DriverResource::getUrl('edit', ['record' => $driver]));
                }),
            Action::make('updateStatus')
                ->label('Alterar estado')
                ->icon('heroicon-o-pencil-square')
                ->form([
                    Select::make('status')
                        ->label('Estado')
                        ->options([
                            'draft' => 'Rascunho',
                            'incomplete' => 'Incompleta',
                            'submitted' => 'Submetida',
                            'converted' => 'Convertida',
                        ])
                        ->required(),
                ])
                ->fillForm(fn (): array => [
                    'status' => $this->record?->status,
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => $data['status'],
                    ]);

                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Estado atualizado')
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gatherDocuments(): array
    {
        $record = $this->record;
        $documents = $record->documents ?? [];

        $labels = [
            'document_id' => 'Documento de identificacao',
            'driver_license' => 'Carta de conducao',
            'tvde_certificate' => 'Certificado TVDE',
            'criminal_record' => 'Registo criminal',
        ];

        $resolved = [];

        foreach ($labels as $key => $label) {
            $entries = CandidateApplicationResource::normalizeDocumentItems($record, $documents[$key] ?? null);

            if ($entries === []) {
                $resolved[] = [
                    'key' => $key,
                    'label' => $label,
                    'path' => null,
                    'exists' => false,
                    'mime' => null,
                    'name' => null,
                    'data_uri' => null,
                    'is_image' => false,
                ];

                continue;
            }

            foreach ($entries as $entry) {
                $path = $entry['path'] ?? null;
                $exists = $path !== null && Storage::disk('public')->exists($path);

                $mime = $exists ? (Storage::disk('public')->mimeType($path) ?: 'application/octet-stream') : null;
                $dataUri = null;

                if ($exists) {
                    $contents = Storage::disk('public')->get($path);
                    $dataUri = 'data:'.($mime ?? 'application/octet-stream').';base64,'.base64_encode($contents);
                }

                $resolved[] = [
                    'key' => $key,
                    'label' => $label,
                    'path' => $path,
                    'exists' => $exists,
                    'mime' => $mime,
                    'name' => $entry['name'] ?? basename((string) ($path ?? $label)),
                    'data_uri' => $dataUri,
                    'is_image' => is_string($mime) && str_starts_with($mime, 'image/'),
                ];
            }
        }

        return $resolved;
    }

    private function logoDataUri(): ?string
    {
        $logoPath = public_path('website/assets/logo.svg');

        if (! file_exists($logoPath)) {
            return null;
        }

        $contents = file_get_contents($logoPath);

        if ($contents === false) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($contents);
    }
}
