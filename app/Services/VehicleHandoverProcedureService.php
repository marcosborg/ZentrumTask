<?php

namespace App\Services;

use App\Mail\VehicleHandoverProceduresMail;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleHandoverProcedure;
use App\Support\VehicleHandoverDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class VehicleHandoverProcedureService
{
    /**
     * @return array<int, array{key: string, label: string, requires_value?: bool, value_label?: string, value_type?: string}>
     */
    public function checklistItems(): array
    {
        return VehicleHandoverDefinition::checklistItems();
    }

    /**
     * @return array<int, string>
     */
    public function damageTypes(): array
    {
        return VehicleHandoverDefinition::damageTypes();
    }

    /**
     * @return array<string, string>
     */
    public function faultTypes(): array
    {
        return VehicleHandoverDefinition::faultTypes();
    }

    /**
     * @return array<int, string>
     */
    public function vehicleZones(): array
    {
        return VehicleHandoverDefinition::vehicleZones();
    }

    /**
     * @return array<int, array{key: string, label: string, view: string, required: bool}>
     */
    public function guidedPhotoZones(): array
    {
        return VehicleHandoverDefinition::guidedPhotoZones();
    }

    public function create(array $data, User $operator): VehicleHandoverProcedure
    {
        $procedure = DB::transaction(fn (): VehicleHandoverProcedure => $this->createProcedure($data, $operator));

        $this->sendProceduresMail(collect([$procedure]));

        return $procedure->refresh();
    }

    public function activeDraft(User $operator): ?VehicleHandoverProcedure
    {
        return VehicleHandoverProcedure::query()
            ->with(['vehicle', 'driver', 'operator'])
            ->where('operator_user_id', $operator->id)
            ->where('status', 'draft')
            ->latest('updated_at')
            ->first();
    }

    public function createDraft(array $data, User $operator): VehicleHandoverProcedure
    {
        if ($draft = $this->activeDraft($operator)) {
            return $draft;
        }

        $vehicle = Vehicle::query()->with('currentAllocation.driver')->findOrFail($data['vehicle_id']);
        $driver = Driver::query()->with('currentAllocation.vehicle')->findOrFail($data['driver_id']);
        $this->guardBusinessRules((string) $data['type'], $vehicle, $driver);

        return VehicleHandoverProcedure::query()->create([
            'type' => $data['type'],
            'status' => 'draft',
            'draft_step' => 'photos',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'operator_user_id' => $operator->id,
            'performed_at' => ! empty($data['performed_at']) ? Carbon::parse($data['performed_at']) : now(),
            'vehicle_snapshot' => $this->vehicleSnapshot($vehicle),
            'driver_snapshot' => $this->driverSnapshot($driver),
            'checklist_payload' => $this->normalizeChecklistPayload([], []),
            'damage_items' => [],
            'fault_items' => [],
            'general_photo_paths' => [],
            'guided_photo_items' => $this->normalizeGuidedPhotoItems([]),
            'video_items' => $this->normalizeVideoItems([]),
            'operator_signature_data_url' => '',
            'driver_signature_data_url' => '',
            'last_synced_at' => now(),
        ]);
    }

    public function updateDraft(VehicleHandoverProcedure $procedure, array $data): VehicleHandoverProcedure
    {
        $this->guardDraft($procedure);
        $updates = ['last_synced_at' => now()];

        if (array_key_exists('draft_step', $data)) {
            $updates['draft_step'] = in_array($data['draft_step'], ['photos', 'videos', 'checklist', 'damage', 'notes', 'signatures', 'review'], true)
                ? $data['draft_step'] : $procedure->draft_step;
        }
        if (array_key_exists('performed_at', $data)) {
            $updates['performed_at'] = Carbon::parse((string) $data['performed_at']);
        }
        if (array_key_exists('checklist_payload', $data)) {
            $updates['checklist_payload'] = $this->normalizeChecklistPayload((array) $data['checklist_payload'], (array) $procedure->guided_photo_items);
        }
        if (array_key_exists('damage_items', $data)) {
            $updates['damage_items'] = $this->normalizeDamageItems((array) $data['damage_items']);
        }
        if (array_key_exists('general_photos', $data)) {
            $updates['general_photo_paths'] = $this->storeGeneralPhotos((array) $data['general_photos']);
        }
        if (array_key_exists('notes', $data)) {
            $updates['notes'] = trim((string) $data['notes']) ?: null;
        }
        if (array_key_exists('operator_signature_data_url', $data)) {
            $updates['operator_signature_data_url'] = (string) $data['operator_signature_data_url'];
        }
        if (array_key_exists('driver_signature_data_url', $data)) {
            $updates['driver_signature_data_url'] = (string) $data['driver_signature_data_url'];
        }

        $procedure->update($updates);

        return $procedure->refresh();
    }

    public function storeDraftMedia(VehicleHandoverProcedure $procedure, string $kind, string $key, mixed $media): VehicleHandoverProcedure
    {
        $this->guardDraft($procedure);

        if ($kind === 'photo') {
            $definition = collect($this->guidedPhotoZones())->firstWhere('key', $key);
            if (! $definition) {
                throw ValidationException::withMessages(['key' => 'Zona fotografica invalida.']);
            }
            $items = (array) $procedure->guided_photo_items;
            $items[$key] = [
                'label' => $definition['label'], 'view' => $definition['view'], 'required' => false,
                'photo_path' => $this->storeSinglePhoto($media, 'vehicle-handovers/guided-photos'),
            ];
            $procedure->update(['guided_photo_items' => $items, 'last_synced_at' => now()]);
        } elseif ($kind === 'video' && in_array($key, ['exterior', 'interior'], true)) {
            $path = $this->storeSingleVideo($media);
            $url = $path ? Storage::disk('public')->url($path) : null;
            $items = (array) $procedure->video_items;
            $items[$key] = [
                'label' => $key === 'exterior' ? 'Video exterior' : 'Video interior', 'required' => false,
                'video_path' => $path, 'qr_path' => $url ? $this->storeQrCode($url, $key) : null, 'url' => $url,
            ];
            $procedure->update(['video_items' => $items, 'last_synced_at' => now()]);
        } else {
            throw ValidationException::withMessages(['kind' => 'Tipo de media invalido.']);
        }

        return $procedure->refresh();
    }

    public function deleteDraft(VehicleHandoverProcedure $procedure): void
    {
        $this->guardDraft($procedure);
        $procedure->delete();
    }

    public function completeDraft(VehicleHandoverProcedure $procedure, User $operator): VehicleHandoverProcedure
    {
        $procedure = DB::transaction(function () use ($procedure): VehicleHandoverProcedure {
            $procedure->refresh();
            $this->guardDraft($procedure);
            if (trim((string) $procedure->driver_signature_data_url) === '') {
                throw ValidationException::withMessages(['driver_signature_data_url' => 'A assinatura do motorista e obrigatoria.']);
            }
            if (trim((string) $procedure->operator_signature_data_url) === '') {
                throw ValidationException::withMessages(['operator_signature_data_url' => 'A assinatura do operador e obrigatoria.']);
            }

            $vehicle = Vehicle::query()->with('currentAllocation.driver')->lockForUpdate()->findOrFail($procedure->vehicle_id);
            $driver = Driver::query()->with('currentAllocation.vehicle')->findOrFail($procedure->driver_id);
            $this->guardBusinessRules($procedure->type, $vehicle, $driver);
            $performedAt = $procedure->performed_at ?? now();

            if ($procedure->type === 'return') {
                $allocation = VehicleAllocation::query()->active()->where('vehicle_id', $vehicle->id)->where('driver_id', $driver->id)->firstOrFail();
                $allocation->update(['ends_at' => $performedAt, 'status' => 'completed']);
                $vehicle->update(['status' => 'available']);
                $procedure->closed_allocation_id = $allocation->id;
                $procedure->allocation_effective_end_date = $performedAt->toDateString();
            } else {
                $allocation = VehicleAllocation::query()->create([
                    'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
                    'starts_at' => $performedAt->copy()->startOfDay()->addDay(), 'status' => 'active',
                    'handover_location' => 'Entrega de viatura', 'notes' => $procedure->notes,
                ]);
                $vehicle->update(['status' => 'allocated']);
                $procedure->created_allocation_id = $allocation->id;
                $procedure->allocation_effective_start_date = Carbon::parse($allocation->starts_at)->toDateString();
            }

            $procedure->status = 'completed';
            $procedure->draft_step = null;
            $procedure->completed_at = now();
            $procedure->last_synced_at = now();
            $procedure->save();

            return $procedure->refresh();
        });

        $this->generateArtifacts($procedure);
        $this->sendProceduresMail(collect([$procedure]));

        return $procedure->refresh();
    }

    protected function guardDraft(VehicleHandoverProcedure $procedure): void
    {
        if ($procedure->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Este auto ja foi concluido.']);
        }
    }

    public function update(VehicleHandoverProcedure $procedure, array $data, User $operator): VehicleHandoverProcedure
    {
        $isDraft = $procedure->status === 'draft';
        if ($isDraft) {
            // A assinatura do motorista e recolhida exclusivamente na app.
            unset($data['driver_signature_data_url']);
        }

        $procedure = DB::transaction(function () use ($data, $operator, $procedure): VehicleHandoverProcedure {
            $vehicle = Vehicle::query()->findOrFail($data['vehicle_id'] ?? $procedure->vehicle_id);
            $driver = Driver::query()->findOrFail($data['driver_id'] ?? $procedure->driver_id);
            $performedAt = isset($data['performed_at']) && $data['performed_at']
                ? Carbon::parse((string) $data['performed_at'])
                : ($procedure->performed_at ?? now());

            $guidedPhotoItems = $this->normalizeGuidedPhotoItems((array) ($data['guided_photo_items'] ?? []));
            $videoItems = $this->normalizeVideoItems((array) ($data['video_items'] ?? []));
            $checklist = $this->normalizeChecklistPayload((array) ($data['checklist_payload'] ?? []), $guidedPhotoItems);
            $damageItems = $this->normalizeDamageItems((array) ($data['damage_items'] ?? []));
            $faultItems = $this->normalizeFaultItems((array) ($data['fault_items'] ?? []));
            $generalPhotoPaths = $this->storeGeneralPhotos((array) ($data['general_photos'] ?? []));

            $procedure->update([
                'type' => $data['type'] ?? $procedure->type,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'operator_user_id' => $procedure->operator_user_id ?: $operator->id,
                'performed_at' => $performedAt,
                'vehicle_snapshot' => $this->vehicleSnapshot($vehicle),
                'driver_snapshot' => $this->driverSnapshot($driver),
                'checklist_payload' => $checklist,
                'damage_items' => $damageItems,
                'fault_items' => $faultItems,
                'general_photo_paths' => $generalPhotoPaths,
                'guided_photo_items' => $guidedPhotoItems,
                'video_items' => $videoItems,
                'battery_minimum_confirmed' => (bool) Arr::get($checklist, 'battery_minimum_agreed.checked', false),
                'battery_minimum_percent' => $this->nullableInt(Arr::get($checklist, 'battery_minimum_agreed.value')),
                'deposit_paid_confirmed' => (bool) Arr::get($checklist, 'deposit_paid.checked', false),
                'deposit_paid_amount' => $this->nullableDecimal(Arr::get($checklist, 'deposit_paid.value')),
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'operator_signature_data_url' => (string) ($data['operator_signature_data_url'] ?? $procedure->operator_signature_data_url),
                'driver_signature_data_url' => (string) ($data['driver_signature_data_url'] ?? $procedure->driver_signature_data_url),
            ]);

            return $procedure->refresh();
        });

        if ($isDraft) {
            $procedure->updateQuietly(['last_synced_at' => now()]);

            return $procedure->refresh();
        }

        try {
            $this->generateArtifacts($procedure);
        } catch (\Throwable $exception) {
            Log::warning('vehicle_handover_artifacts_failed', [
                'procedure_id' => $procedure->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $procedure->refresh();
    }

    private function createProcedure(array $data, User $operator): VehicleHandoverProcedure
    {
        $vehicle = Vehicle::query()->with('currentAllocation.driver')->findOrFail($data['vehicle_id']);
        $driver = Driver::query()->with('currentAllocation.vehicle')->findOrFail($data['driver_id']);
        $performedAt = isset($data['performed_at']) && $data['performed_at']
            ? Carbon::parse((string) $data['performed_at'])
            : now();

        $this->guardBusinessRules($data['type'], $vehicle, $driver);

        $guidedPhotoItems = $this->normalizeGuidedPhotoItems((array) ($data['guided_photo_items'] ?? []));
        $videoItems = $this->normalizeVideoItems((array) ($data['video_items'] ?? []));
        $checklist = $this->normalizeChecklistPayload((array) ($data['checklist_payload'] ?? []), $guidedPhotoItems);
        $damageItems = $this->normalizeDamageItems((array) ($data['damage_items'] ?? []));
        $faultItems = $this->normalizeFaultItems((array) ($data['fault_items'] ?? []));
        $generalPhotoPaths = $this->storeGeneralPhotos((array) ($data['general_photos'] ?? []));

        $procedure = VehicleHandoverProcedure::query()->create([
            'type' => $data['type'],
            'status' => 'completed',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'operator_user_id' => $operator->id,
            'exchange_group_uuid' => $data['exchange_group_uuid'] ?? null,
            'exchange_related_procedure_id' => $data['exchange_related_procedure_id'] ?? null,
            'performed_at' => $performedAt,
            'vehicle_snapshot' => $this->vehicleSnapshot($vehicle),
            'driver_snapshot' => $this->driverSnapshot($driver),
            'checklist_payload' => $checklist,
            'damage_items' => $damageItems,
            'fault_items' => $faultItems,
            'general_photo_paths' => $generalPhotoPaths,
            'guided_photo_items' => $guidedPhotoItems,
            'video_items' => $videoItems,
            'battery_minimum_confirmed' => (bool) Arr::get($checklist, 'battery_minimum_agreed.checked', false),
            'battery_minimum_percent' => $this->nullableInt(Arr::get($checklist, 'battery_minimum_agreed.value')),
            'deposit_paid_confirmed' => (bool) Arr::get($checklist, 'deposit_paid.checked', false),
            'deposit_paid_amount' => $this->nullableDecimal(Arr::get($checklist, 'deposit_paid.value')),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'operator_signature_data_url' => (string) $data['operator_signature_data_url'],
            'driver_signature_data_url' => (string) $data['driver_signature_data_url'],
        ]);

        if ($data['type'] === 'return') {
            $allocation = VehicleAllocation::query()
                ->active()
                ->where('vehicle_id', $vehicle->id)
                ->where('driver_id', $driver->id)
                ->latest('starts_at')
                ->first();

            if ($allocation) {
                $allocation->update([
                    'ends_at' => $performedAt,
                    'status' => 'completed',
                ]);

                $vehicle->update(['status' => 'available']);

                $procedure->update([
                    'closed_allocation_id' => $allocation->id,
                    'allocation_effective_end_date' => $performedAt->toDateString(),
                ]);
            } else {
                Log::warning('vehicle_handover_allocation_not_found', [
                    'procedure_id' => $procedure->id,
                    'type' => $data['type'],
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                ]);
            }
        }

        if ($data['type'] === 'delivery') {
            $allocation = VehicleAllocation::query()
                ->active()
                ->where('vehicle_id', $vehicle->id)
                ->where('driver_id', $driver->id)
                ->latest('starts_at')
                ->first();

            $vehicleHasActiveAllocation = VehicleAllocation::query()
                ->active()
                ->where('vehicle_id', $vehicle->id)
                ->exists();

            $driverHasActiveAllocation = VehicleAllocation::query()
                ->active()
                ->where('driver_id', $driver->id)
                ->exists();

            if (! $allocation && ! $vehicleHasActiveAllocation && ! $driverHasActiveAllocation) {
                $startDate = $performedAt->copy()->startOfDay()->addDay();

                $allocation = VehicleAllocation::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'starts_at' => $startDate,
                    'status' => 'active',
                    'handover_location' => 'Entrega de viatura',
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                ]);
            }

            if ($allocation) {
                $vehicle->update(['status' => 'allocated']);

                $procedure->update([
                    'created_allocation_id' => $allocation->id,
                    'allocation_effective_start_date' => Carbon::parse($allocation->starts_at)->toDateString(),
                ]);
            } else {
                Log::warning('vehicle_handover_allocation_not_created', [
                    'procedure_id' => $procedure->id,
                    'type' => $data['type'],
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'vehicle_has_active_allocation' => $vehicleHasActiveAllocation,
                    'driver_has_active_allocation' => $driverHasActiveAllocation,
                ]);
            }
        }

        $procedure->refresh()->load(['vehicle.currentAllocation.driver', 'driver.currentAllocation.vehicle', 'operator', 'closedAllocation', 'createdAllocation']);

        try {
            $this->generateArtifacts($procedure);
        } catch (\Throwable $exception) {
            Log::warning('vehicle_handover_artifacts_failed', [
                'procedure_id' => $procedure->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $procedure->refresh();
    }

    /**
     * @return array<int, VehicleHandoverProcedure>
     */
    public function recent(int $limit = 20): Collection
    {
        return VehicleHandoverProcedure::query()
            ->with(['vehicle', 'driver', 'operator'])
            ->where('status', 'completed')
            ->latest('performed_at')
            ->limit($limit)
            ->get();
    }

    public function generateArtifacts(VehicleHandoverProcedure $procedure): void
    {
        $procedure->loadMissing(['vehicle', 'driver', 'operator', 'closedAllocation', 'createdAllocation']);

        $html = view('vehicle-handovers.snippet', [
            'procedure' => $procedure,
            'typeLabels' => VehicleHandoverDefinition::typeLabels(),
        ])->render();

        $pdf = Pdf::loadView('pdf.vehicle-handover-procedure', [
            'procedure' => $procedure,
            'typeLabels' => VehicleHandoverDefinition::typeLabels(),
            'logo' => $this->logoDataUri(),
        ])->setPaper('a4');

        $pdf->getDomPDF()->set_option('enable_remote', true);

        $pdfPath = 'vehicle-handovers/pdfs/procedure-'.$procedure->id.'.pdf';
        Storage::disk('public')->put($pdfPath, $pdf->output());

        $procedure->updateQuietly([
            'html_snapshot' => $html,
            'pdf_path' => $pdfPath,
        ]);
    }

    public function generateWorkshopRepairPdf(VehicleHandoverProcedure $procedure): DomPdf
    {
        $procedure->loadMissing(['vehicle', 'driver', 'operator']);

        $pdf = Pdf::loadView('pdf.vehicle-handover-workshop-repair', [
            'procedure' => $procedure,
            'typeLabels' => VehicleHandoverDefinition::typeLabels(),
            'logo' => $this->logoDataUri(),
        ])->setPaper('a4');

        $pdf->getDomPDF()->set_option('enable_remote', true);

        return $pdf;
    }

    /**
     * @return array<string, array{label: string, required: bool, video_path: string|null, qr_path: string|null, url: string|null}>
     */
    protected function normalizeVideoItems(array $items): array
    {
        $definitions = [
            'exterior' => 'Video exterior',
            'interior' => 'Video interior',
        ];

        $normalized = [];

        foreach ($definitions as $key => $label) {
            $payload = Arr::get($items, $key);
            $video = is_array($payload) ? ($payload['video'] ?? null) : $payload;
            $path = $this->storeSingleVideo($video);
            $url = $path ? Storage::disk('public')->url($path) : null;
            $normalized[$key] = [
                'label' => $label,
                'required' => false,
                'video_path' => $path,
                'qr_path' => $url ? $this->storeQrCode($url, $key) : null,
                'url' => $url,
            ];
        }

        return $normalized;
    }

    protected function storeSingleVideo(mixed $video): ?string
    {
        if ($video instanceof UploadedFile) {
            try {
                return $video->store('vehicle-handovers/videos', 'public') ?: null;
            } catch (Throwable $exception) {
                $this->logMediaFailure('video', $exception, [
                    'name' => $video->getClientOriginalName(),
                ]);

                return null;
            }
        }

        if (is_string($video) && trim($video) !== '') {
            return trim($video);
        }

        return null;
    }

    protected function storeQrCode(string $url, string $key): ?string
    {
        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'imageBase64' => false,
                'svgUseFillAttributes' => true,
                'scale' => 4,
            ]);

            $contents = (new QRCode($options))->render($url);
            $path = 'vehicle-handovers/qrcodes/'.uniqid("{$key}_", true).'.svg';
            Storage::disk('public')->put($path, $contents);

            return $path;
        } catch (\Throwable $exception) {
            Log::warning('vehicle_handover_qr_failed', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  Collection<int, VehicleHandoverProcedure>  $procedures
     */
    protected function sendProceduresMail(Collection $procedures): void
    {
        $procedures = $procedures->map->refresh()->values();
        $first = $procedures->first();

        if (! $first instanceof VehicleHandoverProcedure) {
            return;
        }

        $driverEmail = trim((string) ($first->driver?->email ?? data_get($first->driver_snapshot, 'email', '')));
        $recipients = ['info@zentrum-tvde.com'];

        if ($driverEmail !== '' && filter_var($driverEmail, FILTER_VALIDATE_EMAIL)) {
            array_unshift($recipients, $driverEmail);
        }

        $recipients = array_values(array_unique($recipients));

        try {
            Mail::to(array_shift($recipients))
                ->cc($recipients)
                ->send(new VehicleHandoverProceduresMail($procedures));

            $sentTo = array_values(array_unique(array_merge([$driverEmail], $recipients, ['info@zentrum-tvde.com'])));
            $procedures->each(fn (VehicleHandoverProcedure $procedure) => $procedure->updateQuietly([
                'email_sent_at' => now(),
                'email_recipients' => array_values(array_filter($sentTo)),
            ]));
        } catch (\Throwable $exception) {
            Log::warning('vehicle_handover_mail_failed', [
                'procedure_ids' => $procedures->pluck('id')->all(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function guardBusinessRules(string $type, Vehicle $vehicle, Driver $driver): void
    {
        if (! in_array($type, ['delivery', 'return'], true)) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de procedimento invalido.',
            ]);
        }

        if ($type === 'return') {
            if (! $vehicle->currentAllocation) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'A devolucao exige uma viatura com motorista atribuido.',
                ]);
            }

            if ($vehicle->currentAllocation->driver_id !== $driver->id) {
                throw ValidationException::withMessages([
                    'driver_id' => 'O motorista selecionado nao esta atribuido a esta viatura.',
                ]);
            }

            return;
        }

        if ($vehicle->currentAllocation) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'A entrega exige uma viatura sem motorista atribuido. Regista primeiro a devolucao.',
            ]);
        }

        if ($driver->currentAllocation && $driver->currentAllocation->vehicle_id !== $vehicle->id) {
            throw ValidationException::withMessages([
                'driver_id' => 'Este motorista ja tem outra viatura. Regista primeiro a devolucao.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{label: string, view: string, required: bool, photo_path: string|null}>  $guidedPhotoItems
     * @return array<string, array{label: string, checked: bool, value: string|null, value_label: string|null, value_type: string|null}>
     */
    protected function normalizeChecklistPayload(array $payload, array $guidedPhotoItems): array
    {
        $normalized = [];
        $hasRequiredGuidedPhotos = collect($guidedPhotoItems)
            ->filter(fn (array $item): bool => (bool) $item['required'])
            ->every(fn (array $item): bool => ! empty($item['photo_path']));

        foreach ($this->checklistItems() as $item) {
            $itemPayload = Arr::get($payload, $item['key'], []);
            $checked = (bool) Arr::get($itemPayload, 'checked', false);
            $value = Arr::get($itemPayload, 'value');
            $value = is_scalar($value) ? trim((string) $value) : null;

            if ($item['key'] === 'photos_inside_outside') {
                $checked = $hasRequiredGuidedPhotos;
            }

            if ($checked && ($item['requires_value'] ?? false) && ($value === null || $value === '')) {
                throw ValidationException::withMessages([
                    'checklist_payload.'.$item['key'].'.value' => 'Este item exige um valor.',
                ]);
            }

            $normalized[$item['key']] = [
                'label' => $item['label'],
                'checked' => $checked,
                'value' => $value !== '' ? $value : null,
                'value_label' => $item['value_label'] ?? null,
                'value_type' => $item['value_type'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, array{label: string, view: string, required: bool, photo_path: string|null}>
     */
    protected function normalizeGuidedPhotoItems(array $items): array
    {
        $normalized = [];

        foreach ($this->guidedPhotoZones() as $zone) {
            $payload = Arr::get($items, $zone['key'], []);
            $photoPath = $this->storeSinglePhoto(Arr::get($payload, 'photo'), 'vehicle-handovers/guided-photos');

            $normalized[$zone['key']] = [
                'label' => $zone['label'],
                'view' => $zone['view'],
                'required' => $zone['required'],
                'photo_path' => $photoPath,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, string|null>>
     */
    protected function normalizeDamageItems(array $items): array
    {
        return collect($items)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $type = trim((string) ($item['type'] ?? ''));
                $zone = trim((string) ($item['zone'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));

                if ($type === '' && $zone === '' && $description === '') {
                    return null;
                }

                if (! in_array($type, $this->damageTypes(), true)) {
                    throw ValidationException::withMessages([
                        'damage_items' => 'Tipo de dano invalido.',
                    ]);
                }

                if (! in_array($zone, $this->vehicleZones(), true)) {
                    throw ValidationException::withMessages([
                        'damage_items' => 'Zona do dano invalida.',
                    ]);
                }

                return [
                    'type' => $type,
                    'zone' => $zone,
                    'description' => $description !== '' ? $description : null,
                    'photo_path' => $this->storeSinglePhoto($item['photo'] ?? null, 'vehicle-handovers/damage-photos'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{type: string, severity: string|null, description: string|null}>
     */
    protected function normalizeFaultItems(array $items): array
    {
        return collect($items)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $type = trim((string) ($item['type'] ?? ''));
                $severity = trim((string) ($item['severity'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));

                if ($type === '' && $severity === '' && $description === '') {
                    return null;
                }

                if (! array_key_exists($type, $this->faultTypes())) {
                    throw ValidationException::withMessages([
                        'fault_items' => 'Tipo de avaria invalido.',
                    ]);
                }

                if ($severity !== '' && ! in_array($severity, ['low', 'medium', 'high', 'immobilized'], true)) {
                    throw ValidationException::withMessages([
                        'fault_items' => 'Prioridade da avaria invalida.',
                    ]);
                }

                return [
                    'type' => $type,
                    'severity' => $severity !== '' ? $severity : null,
                    'description' => $description !== '' ? $description : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $photos
     * @return array<int, string>
     */
    protected function storeGeneralPhotos(array $photos): array
    {
        return collect($photos)
            ->map(fn (mixed $photo): ?string => $this->storeSinglePhoto($photo, 'vehicle-handovers/general-photos'))
            ->filter()
            ->values()
            ->all();
    }

    protected function storeSinglePhoto(mixed $photo, string $directory): ?string
    {
        if (! is_string($photo) || trim($photo) === '') {
            return null;
        }

        $photo = trim($photo);

        if (! str_starts_with($photo, 'data:image/')) {
            return $photo;
        }

        try {
            if (! str_contains($photo, ',')) {
                return null;
            }

            [$meta, $encoded] = explode(',', $photo, 2);
            preg_match('/^data:image\/([a-zA-Z0-9+]+);base64$/', $meta, $matches);
            $extension = strtolower($matches[1] ?? 'png');
            $contents = base64_decode($encoded, true);

            if ($contents === false) {
                return null;
            }

            $fileName = trim($directory, '/').'/'.uniqid('handover_', true).'.'.$extension;

            if (! Storage::disk('public')->put($fileName, $contents)) {
                return null;
            }

            return $fileName;
        } catch (Throwable $exception) {
            $this->logMediaFailure('photo', $exception, [
                'directory' => $directory,
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logMediaFailure(string $type, Throwable $exception, array $context = []): void
    {
        Log::warning('vehicle_handover_media_failed', array_merge($context, [
            'type' => $type,
            'error' => $exception->getMessage(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function vehicleSnapshot(Vehicle $vehicle): array
    {
        return Arr::only($vehicle->toArray(), [
            'license_plate',
            'make',
            'model',
            'trim',
            'status',
            'source',
            'current_odometer',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function driverSnapshot(Driver $driver): array
    {
        return Arr::only($driver->toArray(), [
            'name',
            'email',
            'phone',
            'nif',
            'license_number',
            'tvde_certificate_number',
            'deposit_amount',
        ]);
    }

    protected function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return number_format((float) str_replace(',', '.', (string) $value), 2, '.', '');
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
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
