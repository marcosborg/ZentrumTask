<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleHandoverProcedure;
use App\Support\VehicleHandoverDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        $vehicle = Vehicle::query()->with('currentAllocation.driver')->findOrFail($data['vehicle_id']);
        $driver = Driver::query()->with('currentAllocation.vehicle')->findOrFail($data['driver_id']);
        $performedAt = isset($data['performed_at']) && $data['performed_at']
            ? Carbon::parse((string) $data['performed_at'])
            : now();

        $this->guardBusinessRules($data['type'], $vehicle, $driver);

        return DB::transaction(function () use ($data, $driver, $operator, $performedAt, $vehicle): VehicleHandoverProcedure {
            $guidedPhotoItems = $this->normalizeGuidedPhotoItems((array) ($data['guided_photo_items'] ?? []));
            $checklist = $this->normalizeChecklistPayload((array) ($data['checklist_payload'] ?? []), $guidedPhotoItems);
            $damageItems = $this->normalizeDamageItems((array) ($data['damage_items'] ?? []));
            $generalPhotoPaths = $this->storeGeneralPhotos((array) ($data['general_photos'] ?? []));

            $procedure = VehicleHandoverProcedure::query()->create([
                'type' => $data['type'],
                'status' => 'completed',
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'operator_user_id' => $operator->id,
                'performed_at' => $performedAt,
                'vehicle_snapshot' => $this->vehicleSnapshot($vehicle),
                'driver_snapshot' => $this->driverSnapshot($driver),
                'checklist_payload' => $checklist,
                'damage_items' => $damageItems,
                'general_photo_paths' => $generalPhotoPaths,
                'guided_photo_items' => $guidedPhotoItems,
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
                    ->firstOrFail();

                $allocation->update([
                    'ends_at' => $performedAt,
                    'status' => 'completed',
                ]);

                $vehicle->update(['status' => 'available']);

                $procedure->update([
                    'closed_allocation_id' => $allocation->id,
                    'allocation_effective_end_date' => $performedAt->toDateString(),
                ]);
            }

            if ($data['type'] === 'delivery') {
                $startDate = $performedAt->copy()->startOfDay()->addDay();

                $allocation = VehicleAllocation::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'starts_at' => $startDate,
                    'status' => 'active',
                    'handover_location' => 'Entrega de viatura',
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                ]);

                $vehicle->update(['status' => 'allocated']);

                $procedure->update([
                    'created_allocation_id' => $allocation->id,
                    'allocation_effective_start_date' => $startDate->toDateString(),
                ]);
            }

            $procedure->refresh()->load(['vehicle.currentAllocation.driver', 'driver.currentAllocation.vehicle', 'operator', 'closedAllocation', 'createdAllocation']);
            $this->generateArtifacts($procedure);

            return $procedure->refresh();
        });
    }

    /**
     * @return array<int, VehicleHandoverProcedure>
     */
    public function recent(int $limit = 20): Collection
    {
        return VehicleHandoverProcedure::query()
            ->with(['vehicle', 'driver', 'operator'])
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

    protected function guardBusinessRules(string $type, Vehicle $vehicle, Driver $driver): void
    {
        if (! in_array($type, ['delivery', 'return'], true)) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de procedimento invalido.',
            ]);
        }

        if ($type === 'delivery') {
            if ($vehicle->status !== 'available' || $vehicle->currentAllocation()->exists()) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'A entrega so pode ser feita com uma viatura disponivel.',
                ]);
            }

            if ($driver->currentAllocation()->exists()) {
                throw ValidationException::withMessages([
                    'driver_id' => 'O motorista ja tem uma viatura alocada.',
                ]);
            }
        }

        if ($type === 'return') {
            $allocation = VehicleAllocation::query()
                ->active()
                ->where('vehicle_id', $vehicle->id)
                ->where('driver_id', $driver->id)
                ->first();

            if (! $allocation) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'A devolucao exige uma alocacao ativa entre viatura e motorista.',
                ]);
            }
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

            if (! $checked) {
                throw ValidationException::withMessages([
                    'checklist_payload.'.$item['key'].'.checked' => 'Todos os itens do checklist sao obrigatorios.',
                ]);
            }

            if (($item['requires_value'] ?? false) && ($value === null || $value === '')) {
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

            if ($zone['required'] && ! $photoPath) {
                throw ValidationException::withMessages([
                    'guided_photo_items.'.$zone['key'].'.photo' => 'Esta fotografia da viatura e obrigatoria.',
                ]);
            }

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

        [$meta, $encoded] = explode(',', $photo, 2);
        preg_match('/^data:image\/([a-zA-Z0-9+]+);base64$/', $meta, $matches);
        $extension = strtolower($matches[1] ?? 'png');
        $contents = base64_decode($encoded, true);

        if ($contents === false) {
            throw ValidationException::withMessages([
                'general_photos' => 'Nao foi possivel processar uma das imagens.',
            ]);
        }

        $fileName = trim($directory, '/').'/'.uniqid('handover_', true).'.'.$extension;
        Storage::disk('public')->put($fileName, $contents);

        return $fileName;
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
