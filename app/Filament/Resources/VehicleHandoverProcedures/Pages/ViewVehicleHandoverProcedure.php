<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Pages;

use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use App\Services\VehicleHandoverProcedureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicleHandoverProcedure extends ViewRecord
{
    protected static string $resource = VehicleHandoverProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('regeneratePdf')
                ->label('Regenerar PDF')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    app(VehicleHandoverProcedureService::class)->generateArtifacts($this->record);

                    $this->record->refresh();

                    Notification::make()
                        ->title('PDF regenerado')
                        ->success()
                        ->send();
                }),
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
            Action::make('downloadWorkshopRepairPdf')
                ->label('Ficha de oficina')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->visible(fn (): bool => collect($this->record->damage_items ?? [])->isNotEmpty() || collect($this->record->fault_items ?? [])->isNotEmpty())
                ->action(function () {
                    $record = $this->record;
                    $pdf = app(VehicleHandoverProcedureService::class)->generateWorkshopRepairPdf($record);

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        'ficha-oficina-'.$record->id.'.pdf'
                    );
                }),
        ];
    }
}
