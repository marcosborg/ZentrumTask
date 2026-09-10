<?php

namespace App\Services;

use App\Filament\Resources\VanReservations\VanReservationResource;
use App\Models\Stage;
use App\Models\Task;
use App\Models\VanReservation;
use Throwable;

class VanRentalKanbanService
{
    public function sync(VanReservation $reservation): void
    {
        try {
            $reservation->getConnection()->transaction(function () use ($reservation): void {
                $reservation = VanReservation::query()->lockForUpdate()->findOrFail($reservation->id);
                if ($reservation->task_id) {
                    return;
                }
                $stage = Stage::query()->whereHas('board', fn ($query) => $query->where('is_active', true))->orderByDesc('is_initial')->orderBy('position')->first();
                if (! $stage) {
                    throw new \RuntimeException('Sem etapa Kanban ativa. Configure um quadro e tente novamente.');
                }
                $task = Task::query()->create([
                    'board_id' => $stage->board_id, 'stage_id' => $stage->id,
                    'title' => 'Aluguer de carrinha: '.$reservation->van->name.' — '.$reservation->name,
                    'description' => $reservation->reference."\n".VanReservationResource::getUrl('edit', ['record' => $reservation], panel: 'admin'),
                    'position' => (int) Task::query()->where('stage_id', $stage->id)->max('position') + 1,
                    'priority' => 'normal',
                    'meta' => ['source' => 'van_rental', 'reservation_id' => $reservation->id, 'reference' => $reservation->reference, 'contact_name' => $reservation->name, 'email' => $reservation->email, 'phone' => $reservation->phone],
                ]);
                $reservation->update(['task_id' => $task->id, 'kanban_error' => null]);
            });
        } catch (Throwable $exception) {
            $reservation->update(['kanban_error' => 'Não foi possível criar a tarefa. Verifique o quadro Kanban e tente novamente.']);
            report($exception);
        }
    }
}
