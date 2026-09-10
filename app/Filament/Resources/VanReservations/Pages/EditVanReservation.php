<?php

namespace App\Filament\Resources\VanReservations\Pages;

use App\Filament\Resources\VanReservations\VanReservationResource;
use App\Services\VanRentalKanbanService;
use App\Services\VanRentalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVanReservation extends EditRecord
{
    protected static string $resource = VanReservationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->getConnection()->transaction(function () use ($record, $data): void {
            $record->update(['internal_notes' => $data['internal_notes'] ?? null]);
            $record->events()->create(['user_id' => auth()->id(), 'from_status' => $record->status, 'to_status' => $record->status, 'reason' => 'Notas internas atualizadas.']);
        });

        return $record;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        foreach (['confirmed' => 'Confirmar', 'rejected' => 'Rejeitar', 'cancelled' => 'Cancelar', 'in_progress' => 'Iniciar', 'completed' => 'Concluir'] as $status => $label) {
            $actions[] = Action::make($status)->label($label)
                ->visible(fn (): bool => in_array($status, ['pending' => ['confirmed', 'rejected', 'cancelled'], 'confirmed' => ['cancelled', 'in_progress'], 'in_progress' => ['completed']][$this->record->status] ?? [], true))
                ->schema([
                    Textarea::make('reason')->label('Motivo / nota de confirmação')->required()->maxLength(2000),
                    TextInput::make('driver_name')->label('Motorista responsável')->maxLength(255)->visible(fn () => $status === 'confirmed' && $this->record->mode === 'with_driver')->required(fn () => $status === 'confirmed' && $this->record->mode === 'with_driver'),
                    Toggle::make('driver_verified')->label('Verifiquei a disponibilidade do motorista')->visible(fn () => $status === 'confirmed' && $this->record->mode === 'with_driver'),
                ])
                ->action(function (array $data) use ($status): void {
                    app(VanRentalService::class)->transition($this->record, $status, auth()->user(), $data['reason'], $data);
                    $this->refreshFormData(['status', 'driver_name', 'history']);
                    Notification::make()->title('Reserva atualizada')->success()->send();
                });
        }
        $actions[] = Action::make('reschedule')->label('Reagendar')->visible(fn () => in_array($this->record->status, ['pending', 'confirmed'], true))->schema([
            TextInput::make('starts_at')->label('Início — Lisboa')->type('datetime-local')->required()->default(fn () => $this->record->starts_at->timezone('Europe/Lisbon')->format('Y-m-d\TH:i')),
            TextInput::make('ends_at')->label('Fim — Lisboa')->type('datetime-local')->required()->default(fn () => $this->record->ends_at->timezone('Europe/Lisbon')->format('Y-m-d\TH:i')),
            Textarea::make('reason')->label('Motivo')->required()->maxLength(2000),
            TextInput::make('driver_name')->label('Motorista no novo horário')->maxLength(255)->visible(fn () => $this->record->status === 'confirmed' && $this->record->mode === 'with_driver')->default(fn () => $this->record->driver_name),
            Toggle::make('driver_verified')->label('Verifiquei a disponibilidade para o novo horário')->visible(fn () => $this->record->status === 'confirmed' && $this->record->mode === 'with_driver'),
        ])->action(function (array $data): void {
            app(VanRentalService::class)->reschedule($this->record, $data['starts_at'], $data['ends_at'], auth()->user(), $data['reason'], $data);
            $this->refreshFormData(['starts_at', 'ends_at', 'estimated_total', 'billable_hours', 'history']);
            Notification::make()->title('Horário atualizado')->success()->send();
        });
        $actions[] = Action::make('retry_kanban')->label('Repetir integração Kanban')->visible(fn () => ! $this->record->task_id)->action(function (): void {
            app(VanRentalKanbanService::class)->sync($this->record);
            $this->refreshFormData(['kanban_error', 'task_id']);
            Notification::make()->title($this->record->fresh()->task_id ? 'Tarefa criada' : 'Integração pendente; consulte o erro')->send();
        });

        return $actions;
    }
}
