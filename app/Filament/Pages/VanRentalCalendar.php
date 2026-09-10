<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VanReservations\VanReservationResource;
use App\Models\RentalVan;
use App\Models\VanBlock;
use App\Models\VanReservation;
use App\Services\VanRentalService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

class VanRentalCalendar extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 52;

    protected static ?string $title = 'Calendário de carrinhas';

    protected string $view = 'filament.pages.van-rental-calendar';

    public string $date = '';

    public string $period = 'month';

    public string $vanId = '';

    public function mount(): void
    {
        $this->date = now('Europe/Lisbon')->format('Y-m-d');
    }

    public function move(int $direction): void
    {
        $this->validateFilters();
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $date = CarbonImmutable::parse($this->date);
        $this->date = ($this->period === 'week' ? $date->addWeeks($direction) : $date->addMonthsNoOverflow($direction))->format('Y-m-d');
    }

    private function validateFilters(): void
    {
        $this->validate(['date' => ['required', 'date_format:Y-m-d'], 'period' => ['required', 'in:month,week'], 'vanId' => ['nullable', 'integer', 'exists:rental_vans,id']]);
    }

    #[Computed]
    public function calendar(): array
    {
        $this->validateFilters();
        $date = CarbonImmutable::parse($this->date, 'Europe/Lisbon');
        $start = $this->period === 'week' ? $date->startOfWeek() : $date->startOfMonth()->startOfWeek();
        $end = $this->period === 'week' ? $start->addWeek() : $date->endOfMonth()->endOfWeek()->addDay()->startOfDay();
        $reservations = VanReservation::query()->with('van')->when($this->vanId, fn ($q) => $q->where('rental_van_id', $this->vanId))->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->whereIn('status', ['pending', 'confirmed', 'in_progress'])->get();
        $blocks = VanBlock::query()->with('van')->when($this->vanId, fn ($q) => $q->where('rental_van_id', $this->vanId))->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->get();
        $days = [];
        for ($day = $start; $day < $end; $day = $day->addDay()) {
            $items = [];
            foreach ($reservations as $reservation) {
                if ($reservation->starts_at < $day->addDay()->utc() && $reservation->ends_at > $day->utc()) {
                    $items[] = ['label' => $reservation->van->name, 'time' => $reservation->starts_at->timezone('Europe/Lisbon')->format('d/m H:i').' → '.$reservation->ends_at->timezone('Europe/Lisbon')->format('d/m H:i'), 'state' => VanReservation::STATUSES[$reservation->status], 'pending' => $reservation->status === 'pending', 'url' => VanReservationResource::getUrl('edit', ['record' => $reservation])];
                }
            }
            foreach ($blocks as $block) {
                if ($block->starts_at < $day->addDay()->utc() && $block->ends_at > $day->utc()) {
                    $items[] = ['label' => $block->van->name, 'time' => $block->starts_at->timezone('Europe/Lisbon')->format('d/m H:i').' → '.$block->ends_at->timezone('Europe/Lisbon')->format('d/m H:i'), 'state' => 'Bloqueio: '.$block->reason, 'pending' => false, 'url' => null];
                }
            }
            $days[] = ['date' => $day, 'items' => $items];
        }

        return $days;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('block')->label('Bloquear período')->schema([
                Select::make('van_id')->label('Carrinha')->options(fn () => RentalVan::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                TextInput::make('starts_at')->type('datetime-local')->label('Início — hora local')->required(),
                TextInput::make('ends_at')->type('datetime-local')->label('Fim — hora local')->required(),
                Textarea::make('reason')->label('Motivo interno')->required()->maxLength(255),
            ])->action(function (array $data): void {
                app(VanRentalService::class)->block(RentalVan::query()->findOrFail($data['van_id']), $data['starts_at'], $data['ends_at'], $data['reason'], auth()->user());
                unset($this->calendar);
                Notification::make()->title('Período bloqueado')->success()->send();
            }),
            Action::make('unblock')->label('Remover bloqueio')->schema([
                Select::make('block_id')->label('Bloqueio')->options(fn () => VanBlock::query()->with('van')->orderByDesc('starts_at')->get()->mapWithKeys(fn ($block) => [$block->id => $block->van->name.' · '.$block->starts_at->timezone('Europe/Lisbon')->format('d/m/Y H:i').' · '.$block->reason]))->searchable()->required(),
            ])->requiresConfirmation()->action(function (array $data): void {
                VanBlock::query()->findOrFail($data['block_id'])->delete();
                unset($this->calendar);
                Notification::make()->title('Bloqueio removido')->success()->send();
            }),
        ];
    }
}
