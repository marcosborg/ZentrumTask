<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Pages;

use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicleHandoverProcedure extends ViewRecord
{
    protected static string $resource = VehicleHandoverProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Descarregar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $record = $this->record;

                    if ($record->pdf_path && \Storage::disk('public')->exists($record->pdf_path)) {
                        return response()->download(\Storage::disk('public')->path($record->pdf_path), 'procedimento-'.$record->id.'.pdf');
                    }

                    $pdf = Pdf::loadView('pdf.vehicle-handover-procedure', [
                        'procedure' => $record,
                        'typeLabels' => \App\Support\VehicleHandoverDefinition::typeLabels(),
                        'logo' => null,
                    ])->setPaper('a4');

                    $pdf->getDomPDF()->set_option('enable_remote', true);

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        'procedimento-'.$record->id.'.pdf'
                    );
                }),
        ];
    }
}
