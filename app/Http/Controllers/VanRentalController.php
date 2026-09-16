<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVanReservationRequest;
use App\Http\Requests\VanAvailabilityRequest;
use App\Models\RentalVan;
use App\Services\VanRentalKanbanService;
use App\Services\VanRentalService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VanRentalController extends Controller
{
    public function index(VanAvailabilityRequest $request, VanRentalService $service): View
    {
        $data = $request->validated();
        $vans = RentalVan::query()->where('status', 'published')
            ->when($data['mode'] ?? null, fn ($query, $mode) => $query->where($mode, true))
            ->when($data['capacity'] ?? null, fn ($query, $capacity) => $query->where('volume_m3', '>=', $capacity))
            ->orderByDesc('featured')->orderBy('name')->get();
        if (! empty($data['starts_at']) && ! empty($data['ends_at'])) {
            $start = $service->date($data['starts_at']);
            $end = $service->date($data['ends_at'], 'ends_at');
            if ($end <= $start || $start->isPast() || $end > $start->addDays(30)) {
                throw ValidationException::withMessages(['ends_at' => 'Selecione um período futuro válido, até 30 dias.']);
            }
            $vans->load(['reservations' => fn ($query) => $query->whereIn('status', ['confirmed', 'in_progress']), 'blocks']);
            $vans = $vans->filter(function (RentalVan $van) use ($service, $data): bool {
                try {
                    $service->quote($van, [...$data, 'mode' => $data['mode'] ?? ($van->self_drive ? 'self_drive' : 'with_driver')]);

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            });
        }

        return view('website.vans.index', compact('vans'));
    }

    public function show(RentalVan $van, ?string $slug = null): View|RedirectResponse
    {
        abort_unless($van->status === 'published', 404);
        if ($slug !== Str::slug($van->name)) {
            return redirect($van->publicUrl());
        }
        $key = (string) Str::uuid();
        $keys = session('van-rental-keys', []);
        $keys[$key] = $van->id;
        session(['van-rental-keys' => array_slice($keys, -20, null, true)]);

        return view('website.vans.show', ['van' => $van, 'submissionKey' => $key]);
    }

    public function availability(VanAvailabilityRequest $request, RentalVan $van): JsonResponse
    {
        abort_unless($van->status === 'published', 404);
        $month = CarbonImmutable::parse(($request->validated('month') ?? now()->format('Y-m')).'-01', VanRentalService::TIMEZONE)->startOfMonth();
        $from = $month->utc();
        $until = $month->addMonth()->utc();
        $periods = $van->reservations()->whereIn('status', ['confirmed', 'in_progress'])
            ->where('starts_at', '<', $until->addDays(3))->where('ends_at', '>', $from->subDays(3))->get()
            ->map(fn ($reservation): array => ['start' => $reservation->starts_at->subMinutes(max($van->buffer_minutes, $reservation->buffer_minutes))->toIso8601String(), 'end' => $reservation->ends_at->addMinutes(max($van->buffer_minutes, $reservation->buffer_minutes))->toIso8601String()]);
        $blocks = $van->blocks()->where('starts_at', '<', $until->addDays(3))->where('ends_at', '>', $from->subDays(3))->get()
            ->map(fn ($block): array => ['start' => $block->starts_at->subMinutes($van->buffer_minutes)->toIso8601String(), 'end' => $block->ends_at->addMinutes($van->buffer_minutes)->toIso8601String()]);

        return response()->json(['periods' => $periods->concat($blocks)->values(), 'timezone' => VanRentalService::TIMEZONE]);
    }

    public function quote(VanAvailabilityRequest $request, RentalVan $van, VanRentalService $service): JsonResponse
    {
        abort_unless($van->status === 'published', 404);
        $quote = $service->quote($van, $request->validated());

        return response()->json(collect($quote)->only(['hourly_rate', 'billable_hours', 'pricing_unit', 'estimated_total', 'deposit'])->all());
    }

    public function store(StoreVanReservationRequest $request, RentalVan $van, VanRentalService $service, VanRentalKanbanService $kanban): RedirectResponse
    {
        abort_unless($van->status === 'published', 404);
        abort_unless((int) session('van-rental-keys.'.$request->validated('submission_key')) === $van->id, 419);
        $reservation = $service->reserve($van, $request->validated());
        $kanban->sync($reservation);

        return redirect($van->publicUrl())->with('van_success', $reservation->reference);
    }
}
