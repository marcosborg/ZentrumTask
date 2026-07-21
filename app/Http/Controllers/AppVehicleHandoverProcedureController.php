<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleHandoverMediaRequest;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use App\Support\VehicleHandoverDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AppVehicleHandoverProcedureController extends AppApiController
{
    public function __construct(
        private readonly VehicleHandoverProcedureService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $vehicles = Vehicle::query()
            ->with(['currentAllocation.driver'])
            ->orderBy('license_plate')
            ->get();

        $drivers = Driver::query()
            ->with(['currentAllocation.vehicle'])
            ->orderBy('name')
            ->get();

        return $this->corsJson([
            'checklist_items' => $this->service->checklistItems(),
            'damage_types' => collect($this->service->damageTypes())
                ->map(fn (string $value): array => ['value' => $value, 'label' => ucfirst($value)])
                ->values()
                ->all(),
            'fault_types' => collect($this->service->faultTypes())
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'guided_photo_zones' => collect($this->service->guidedPhotoZones())
                ->map(fn (array $zone): array => [
                    'key' => $zone['key'],
                    'label' => $zone['label'],
                    'view' => $zone['view'],
                    'required' => $zone['required'],
                ])
                ->values()
                ->all(),
            'vehicle_zones' => collect($this->service->vehicleZones())
                ->map(fn (string $value): array => ['value' => $value, 'label' => str_replace('_', ' ', ucfirst($value))])
                ->values()
                ->all(),
            'vehicles' => $vehicles->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
                'display_name' => trim(implode(' ', array_filter([$vehicle->license_plate, $vehicle->make, $vehicle->model]))),
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'status' => $vehicle->status,
                'status_label' => match ($vehicle->status) {
                    'available' => 'Disponivel',
                    'allocated' => 'Alocada',
                    'maintenance' => 'Manutencao',
                    'accident' => 'Acidente',
                    'sold' => 'Vendida',
                    'inactive' => 'Inativa',
                    default => (string) $vehicle->status,
                },
                'current_driver_id' => $vehicle->currentAllocation?->driver?->id,
                'current_driver_name' => $vehicle->currentAllocation?->driver?->name,
            ])->values()->all(),
            'drivers' => $drivers->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'name' => $driver->name,
                'display_name' => trim(implode(' - ', array_filter([$driver->name, $driver->phone]))),
                'phone' => $driver->phone,
                'email' => $driver->email,
                'current_vehicle_id' => $driver->currentAllocation?->vehicle?->id,
                'current_vehicle_license_plate' => $driver->currentAllocation?->vehicle?->license_plate,
            ])->values()->all(),
            'recent_procedures' => $this->service->recent()
                ->map(fn (VehicleHandoverProcedure $procedure): array => $this->serializeProcedureSummary($procedure))
                ->values()
                ->all(),
            'active_draft' => ($draft = $this->service->activeDraft($user))
                ? $this->serializeProcedureDetail($draft)
                : null,
        ]);
    }

    public function storeDraft(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);
        if (! $user) {
            return $this->corsJson(['message' => 'Sessao invalida.'], 401);
        }

        try {
            $procedure = $this->service->createDraft($request->all(), $user);
        } catch (ValidationException $exception) {
            return $this->corsJson(['message' => 'Nao foi possivel criar o rascunho.', 'errors' => $exception->errors()], 422);
        }

        return $this->corsJson(['procedure' => $this->serializeProcedureDetail($procedure)], 201);
    }

    public function updateDraft(Request $request, VehicleHandoverProcedure $vehicleHandoverProcedure): JsonResponse
    {
        $user = $this->resolveAppUser($request);
        if (! $user || $vehicleHandoverProcedure->operator_user_id !== $user->id) {
            return $this->corsJson(['message' => 'Rascunho nao encontrado.'], $user ? 404 : 401);
        }

        try {
            $procedure = $this->service->updateDraft($vehicleHandoverProcedure, $request->all());
        } catch (ValidationException $exception) {
            return $this->corsJson(['message' => 'Nao foi possivel guardar.', 'errors' => $exception->errors()], 422);
        }

        return $this->corsJson(['procedure' => $this->serializeProcedureDetail($procedure)]);
    }

    public function storeDraftMedia(Request $request, VehicleHandoverProcedure $vehicleHandoverProcedure): JsonResponse
    {
        $user = $this->resolveAppUser($request);
        if (! $user || $vehicleHandoverProcedure->operator_user_id !== $user->id) {
            return $this->corsJson(['message' => 'Rascunho nao encontrado.'], $user ? 404 : 401);
        }

        try {
            $media = $request->file('media') ?? $request->input('media');
            $procedure = $this->service->storeDraftMedia($vehicleHandoverProcedure, (string) $request->input('kind'), (string) $request->input('key'), $media);
        } catch (ValidationException $exception) {
            return $this->corsJson(['message' => 'Nao foi possivel guardar o ficheiro.', 'errors' => $exception->errors()], 422);
        }

        return $this->corsJson(['procedure' => $this->serializeProcedureDetail($procedure)]);
    }

    public function complete(Request $request, VehicleHandoverProcedure $vehicleHandoverProcedure): JsonResponse
    {
        $user = $this->resolveAppUser($request);
        if (! $user || $vehicleHandoverProcedure->operator_user_id !== $user->id) {
            return $this->corsJson(['message' => 'Rascunho nao encontrado.'], $user ? 404 : 401);
        }

        try {
            $procedure = $this->service->completeDraft($vehicleHandoverProcedure, $user);
        } catch (ValidationException $exception) {
            return $this->corsJson(['message' => 'Nao foi possivel concluir o auto.', 'errors' => $exception->errors()], 422);
        }

        return $this->corsJson(['procedure' => $this->serializeProcedureDetail($procedure)]);
    }

    public function destroyDraft(Request $request, VehicleHandoverProcedure $vehicleHandoverProcedure): JsonResponse
    {
        $user = $this->resolveAppUser($request);
        if (! $user || $vehicleHandoverProcedure->operator_user_id !== $user->id) {
            return $this->corsJson(['message' => 'Rascunho nao encontrado.'], $user ? 404 : 401);
        }

        $this->service->deleteDraft($vehicleHandoverProcedure);

        return $this->corsJson(['message' => 'Rascunho eliminado.']);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        try {
            $procedure = $this->service->create($request->all(), $user);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel guardar o procedimento.',
                'errors' => $exception->errors(),
            ], 422);
        }

        return $this->corsJson([
            'message' => 'Procedimento registado com sucesso.',
            'procedure' => $this->serializeProcedureDetail($procedure),
        ], 201);
    }

    public function storeMedia(StoreVehicleHandoverMediaRequest $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $path = $request->file('video')?->store('vehicle-handovers/videos', 'public');

        if (! $path) {
            return $this->corsJson([
                'message' => 'Nao foi possivel guardar o video.',
            ], 422);
        }

        return $this->corsJson([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }

    public function show(Request $request, VehicleHandoverProcedure $vehicleHandoverProcedure): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        return $this->corsJson([
            'procedure' => $this->serializeProcedureDetail($vehicleHandoverProcedure),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProcedureSummary(VehicleHandoverProcedure $procedure): array
    {
        return [
            'id' => $procedure->id,
            'type' => $procedure->type,
            'type_label' => VehicleHandoverDefinition::typeLabels()[$procedure->type] ?? $procedure->type,
            'performed_at' => optional($procedure->performed_at)?->toIso8601String(),
            'performed_at_label' => optional($procedure->performed_at)?->format('d/m/Y H:i'),
            'vehicle' => [
                'id' => $procedure->vehicle?->id,
                'license_plate' => $procedure->vehicle?->license_plate ?? data_get($procedure->vehicle_snapshot, 'license_plate'),
                'display_name' => trim(implode(' ', array_filter([
                    $procedure->vehicle?->license_plate ?? data_get($procedure->vehicle_snapshot, 'license_plate'),
                    $procedure->vehicle?->make ?? data_get($procedure->vehicle_snapshot, 'make'),
                    $procedure->vehicle?->model ?? data_get($procedure->vehicle_snapshot, 'model'),
                ]))),
            ],
            'driver' => [
                'id' => $procedure->driver?->id,
                'name' => $procedure->driver?->name ?? data_get($procedure->driver_snapshot, 'name'),
                'phone' => $procedure->driver?->phone ?? data_get($procedure->driver_snapshot, 'phone'),
            ],
            'operator_name' => $procedure->operator?->name,
            'notes' => $procedure->notes,
            'pdf_url' => $procedure->pdf_path ? Storage::disk('public')->url($procedure->pdf_path) : null,
            'exchange_group_uuid' => $procedure->exchange_group_uuid,
            'exchange_related_procedure_id' => $procedure->exchange_related_procedure_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProcedureDetail(VehicleHandoverProcedure $procedure): array
    {
        $procedure->loadMissing(['vehicle', 'driver', 'operator', 'closedAllocation', 'createdAllocation']);

        $generalPhotoUrls = collect($procedure->general_photo_paths ?? [])
            ->map(fn (?string $path): ?string => $path ? Storage::disk('public')->url($path) : null)
            ->filter()
            ->values()
            ->all();

        $damageItems = collect($procedure->damage_items ?? [])
            ->map(fn (array $item): array => [
                'type' => $item['type'] ?? null,
                'zone' => $item['zone'] ?? null,
                'description' => $item['description'] ?? null,
                'photo_path' => $item['photo_path'] ?? null,
                'photo_url' => ! empty($item['photo_path']) ? Storage::disk('public')->url($item['photo_path']) : null,
            ])
            ->values()
            ->all();

        return array_merge($this->serializeProcedureSummary($procedure), [
            'status' => $procedure->status,
            'draft_step' => $procedure->draft_step,
            'completed_at' => optional($procedure->completed_at)?->toIso8601String(),
            'last_synced_at' => optional($procedure->last_synced_at)?->toIso8601String(),
            'performed_date_label' => optional($procedure->performed_at)?->format('d/m/Y'),
            'allocation_effective_start_date' => optional($procedure->allocation_effective_start_date)?->toDateString(),
            'allocation_effective_start_date_label' => optional($procedure->allocation_effective_start_date)?->format('d/m/Y'),
            'allocation_effective_end_date' => optional($procedure->allocation_effective_end_date)?->toDateString(),
            'allocation_effective_end_date_label' => optional($procedure->allocation_effective_end_date)?->format('d/m/Y'),
            'vehicle_snapshot' => $procedure->vehicle_snapshot,
            'driver_snapshot' => $procedure->driver_snapshot,
            'checklist_payload' => $procedure->checklist_payload,
            'damage_items' => $damageItems,
            'fault_items' => collect($procedure->fault_items ?? [])
                ->map(fn (array $item): array => [
                    'type' => $item['type'] ?? null,
                    'type_label' => $this->service->faultTypes()[$item['type'] ?? ''] ?? ($item['type'] ?? null),
                    'severity' => $item['severity'] ?? null,
                    'description' => $item['description'] ?? null,
                ])
                ->values()
                ->all(),
            'general_photo_urls' => $generalPhotoUrls,
            'general_photo_paths' => $procedure->general_photo_paths ?? [],
            'guided_photo_items' => collect($procedure->guided_photo_items ?? [])
                ->map(fn (array $item, string $key): array => [
                    'key' => $key,
                    'label' => $item['label'] ?? $key,
                    'view' => $item['view'] ?? null,
                    'required' => (bool) ($item['required'] ?? false),
                    'photo_url' => ! empty($item['photo_path']) ? Storage::disk('public')->url($item['photo_path']) : null,
                ])
                ->values()
                ->all(),
            'video_items' => collect($procedure->video_items ?? [])
                ->map(fn (array $item, string $key): array => [
                    'key' => $key,
                    'label' => $item['label'] ?? $key,
                    'required' => (bool) ($item['required'] ?? false),
                    'video_url' => ! empty($item['video_path']) ? Storage::disk('public')->url($item['video_path']) : ($item['url'] ?? null),
                    'qr_url' => ! empty($item['qr_path']) ? Storage::disk('public')->url($item['qr_path']) : null,
                ])
                ->values()
                ->all(),
            'battery_minimum_confirmed' => $procedure->battery_minimum_confirmed,
            'battery_minimum_percent' => $procedure->battery_minimum_percent,
            'deposit_paid_confirmed' => $procedure->deposit_paid_confirmed,
            'deposit_paid_amount' => $procedure->deposit_paid_amount,
            'operator_signature_data_url' => $procedure->operator_signature_data_url,
            'driver_signature_data_url' => $procedure->driver_signature_data_url,
            'html_snapshot' => $procedure->html_snapshot,
            'created_allocation_id' => $procedure->created_allocation_id,
            'closed_allocation_id' => $procedure->closed_allocation_id,
            'email_sent_at' => optional($procedure->email_sent_at)?->toIso8601String(),
            'email_recipients' => $procedure->email_recipients,
        ]);
    }
}
