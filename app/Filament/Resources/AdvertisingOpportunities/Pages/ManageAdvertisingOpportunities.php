<?php

namespace App\Filament\Resources\AdvertisingOpportunities\Pages;

use App\Filament\Resources\AdvertisingOpportunities\AdvertisingOpportunityResource;
use App\Models\Board;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageAdvertisingOpportunities extends ManageRecords
{
    protected static string $resource = AdvertisingOpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(function (array $data): array {
                $data['board_id'] = Board::query()->where('slug', 'zentrum-ads')->firstOrFail()->id;
                $data['meta'] = ['source' => 'manual'];

                return $data;
            }),
            Action::make('export')->label('Exportar CSV')->action(function (): StreamedResponse {
                return response()->streamDownload(function (): void {
                    $stream = fopen('php://output', 'w');
                    fputcsv($stream, ['Empresa', 'Email', 'Telefone', 'Estado', 'Responsável', 'Próximo contacto', 'Origem'], ';');
                    foreach (AdvertisingOpportunityResource::getEloquentQuery()->with(['assignedTo', 'stage'])->lazyById() as $task) {
                        $cells = [$task->title, $task->meta['email'] ?? '', $task->meta['phone'] ?? '', $task->stage?->name, $task->assignedTo?->name, $task->due_at?->toDateString(), data_get($task->meta, 'utm.utm_source', $task->meta['source'] ?? '')];
                        fputcsv($stream, array_map(fn ($cell): string => preg_match('/^[\s]*[=+\-@]/', (string) $cell) ? "'".$cell : (string) $cell, $cells), ';');
                    }
                    fclose($stream);
                }, 'zentrum-ads.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            }),
        ];
    }
}
