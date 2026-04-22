<?php

namespace App\Http\Controllers;

use App\Mail\ReservationPaymentInstructionsMail;
use App\Models\CandidateApplication;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\IfthenpayMultibancoService;
use App\Support\ReservationOfferContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CandidateApplicationController extends Controller
{
    public function show(Request $request)
    {
        $preselectedVehicle = $this->resolvePreselectedVehicle($request);
        $application = $this->resolveApplication($request, $preselectedVehicle);
        $paymentService = app(IfthenpayMultibancoService::class);

        return view('candidatura.wizard', [
            'application' => $application,
            'uploadEndpoint' => route('reserva.upload'),
            'saveEndpoint' => route('reserva.save'),
            'submitEndpoint' => route('reserva.submit'),
            'paymentEndpoint' => route('reserva.payment'),
            'vehicleTypes' => VehicleType::orderBy('brand')->orderBy('model')->get(),
            'preselectedVehicle' => $preselectedVehicle,
            'initialPayment' => $paymentService->getReferenceData($application),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $application = $this->findByToken($request->input('token'));

        $step = (string) $request->input('step', '');

        $data = $this->validatedDataForStep($step, $request);

        $application->fill($data);
        $application->status = $application->submitted_at ? 'submitted' : 'incomplete';
        $application->current_step = $step;
        $application->last_ip = $request->ip();
        $application->last_saved_at = now();
        $application->save();

        return response()->json([
            'status' => 'ok',
            'token' => $application->token,
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $application = $this->findByToken($request->input('token'));
        $wasAlreadySubmitted = $application->submitted_at !== null;

        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:30'],
            'nif' => ['required', 'string', 'max:30'],
            'accepts_model' => ['accepted'],
            'independent_driver' => ['accepted'],
            'rental_terms_read' => ['accepted'],
            'rental_terms_accept' => ['accepted'],
            'has_tvde_course' => ['required', 'boolean'],
            'certificate_valid' => ['required', 'boolean'],
            'experience' => ['required', 'string', 'max:255'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'max:50'],
            'iban' => ['required', 'string', 'max:34'],
            'vehicle_type_id' => ['required', 'exists:vehicle_types,id'],
            'rgpd' => ['accepted'],
            'truth_declaration' => ['accepted'],
            'contact_authorization' => ['accepted'],
        ];

        $validated = $request->validate($rules);

        $application->fill($validated);
        $application->status = 'submitted';
        $application->submitted_at = now();
        $application->last_ip = $request->ip();
        $application->legal_confirmed_at = now();
        $application->legal_ip = $request->ip();
        $application->legal_version = 'v1';
        $application->save();

        $application->loadMissing('vehicleType');

        $payment = app(IfthenpayMultibancoService::class)->ensureReference($application);

        if (! $wasAlreadySubmitted && filled($application->email)) {
            try {
                Mail::to($application->email)->send(
                    new ReservationPaymentInstructionsMail(
                        $application->fresh('vehicleType'),
                        $payment,
                        ReservationOfferContent::data(),
                    )
                );
            } catch (\Throwable $exception) {
                Log::warning('reservation_payment_email_failed', [
                    'application_id' => $application->getKey(),
                    'email' => $application->email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 'submitted',
            'token' => $application->token,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $application = $this->findByToken($request->input('token'));

        $request->validate([
            'field' => ['required', Rule::in(['document_id', 'driver_license', 'tvde_certificate', 'criminal_record'])],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $field = $request->input('field');
        $file = $request->file('file');

        $path = $file->store("applications/{$application->token}", 'public');

        $documents = $application->documents ?? [];
        $current = $this->normalizeDocumentEntries($documents[$field] ?? null);
        $document = [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
        ];
        $current[] = $document;
        $documents[$field] = $current;

        $application->documents = $documents;
        $application->status = $application->submitted_at ? 'submitted' : 'incomplete';
        $application->last_saved_at = now();
        $application->save();

        return response()->json([
            'status' => 'ok',
            'field' => $field,
            'document' => $document,
            'documents' => $documents[$field],
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function payment(Request $request, IfthenpayMultibancoService $paymentService): JsonResponse
    {
        $application = $this->findByToken($request->input('token'));

        $payment = $paymentService->ensureReference($application);

        return response()->json([
            'status' => 'ok',
            'payment' => $payment,
        ]);
    }

    public function paymentCallback(Request $request, IfthenpayMultibancoService $paymentService)
    {
        $application = $paymentService->handleCallback($request->all());

        if (! $application) {
            return response('invalid', 422);
        }

        return response('ok');
    }

    protected function resolveApplication(Request $request, ?Vehicle $preselectedVehicle = null): CandidateApplication
    {
        if ($token = $request->session()->get('candidate_token')) {
            $existing = CandidateApplication::where('token', $token)->first();
            if ($existing) {
                return $this->applyPreselectedVehicle($existing, $preselectedVehicle);
            }
        }

        if ($token = $request->query('token')) {
            $existing = CandidateApplication::where('token', $token)->first();
            if ($existing) {
                $request->session()->put('candidate_token', $token);

                return $this->applyPreselectedVehicle($existing, $preselectedVehicle);
            }
        }

        $application = CandidateApplication::create([
            'status' => 'draft',
            'current_step' => 'welcome',
            'last_ip' => $request->ip(),
            'last_saved_at' => now(),
        ]);

        $request->session()->put('candidate_token', $application->token);

        return $this->applyPreselectedVehicle($application, $preselectedVehicle);
    }

    protected function findByToken(?string $token): CandidateApplication
    {
        $application = CandidateApplication::where('token', $token)->firstOrFail();

        return $application;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedDataForStep(string $step, Request $request): array
    {
        return match ($step) {
            'welcome' => $request->validate([
                'accepts_model' => ['accepted'],
                'independent_driver' => ['accepted'],
            ]),
            'vehicle' => $request->validate([
                'vehicle_type_id' => ['required', 'exists:vehicle_types,id'],
            ]),
            'rental' => $request->validate([
                'rental_terms_read' => ['accepted'],
                'rental_terms_accept' => ['accepted'],
            ]) + [
                'rental_terms_accepted_at' => now(),
                'rental_terms_ip' => $request->ip(),
            ],
            'eligibility' => $request->validate([
                'has_tvde_course' => ['required', 'boolean'],
                'certificate_valid' => ['required', 'boolean'],
                'experience' => ['required', 'string', 'max:255'],
                'platforms' => ['required', 'array', 'min:1'],
                'platforms.*' => ['string', 'max:50'],
            ]),
            'personal' => $request->validate([
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email'],
                'phone' => ['required', 'string', 'max:30'],
                'nif' => ['required', 'string', 'max:30'],
                'iban' => ['required', 'string', 'max:34'],
            ]),
            'summary' => $request->validate([
                'rgpd' => ['accepted'],
                'truth_declaration' => ['accepted'],
                'contact_authorization' => ['accepted'],
            ]) + [
                'legal_confirmed_at' => now(),
                'legal_ip' => $request->ip(),
                'legal_version' => 'v1',
            ],
            default => $request->validate([]),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDocumentEntries(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = [];

        if (is_array($value)) {
            $candidates = array_is_list($value) ? $value : [$value];

            foreach ($candidates as $candidate) {
                if (is_array($candidate)) {
                    $path = $candidate['path'] ?? null;
                    if (! is_string($path) || $path === '') {
                        continue;
                    }

                    $items[] = [
                        'path' => $path,
                        'name' => $candidate['name'] ?? basename($path),
                        'mime' => $candidate['mime'] ?? null,
                        'size' => $candidate['size'] ?? null,
                        'uploaded_at' => $candidate['uploaded_at'] ?? null,
                    ];

                    continue;
                }

                if (is_string($candidate) && $candidate !== '') {
                    $items[] = [
                        'path' => $candidate,
                        'name' => basename($candidate),
                    ];
                }
            }

            return $items;
        }

        if (is_string($value)) {
            return [[
                'path' => $value,
                'name' => basename($value),
            ]];
        }

        return [];
    }

    protected function resolvePreselectedVehicle(Request $request): ?Vehicle
    {
        $vehicleId = $request->integer('vehicle');

        if (! $vehicleId) {
            return null;
        }

        return Vehicle::query()
            ->where('source', 'tvde')
            ->find($vehicleId);
    }

    protected function applyPreselectedVehicle(CandidateApplication $application, ?Vehicle $vehicle): CandidateApplication
    {
        if (! $vehicle || $application->submitted_at) {
            return $application;
        }

        $vehicleTypeId = $this->resolveVehicleTypeIdFromVehicle($vehicle);

        if (! $vehicleTypeId) {
            return $application;
        }

        if ((int) $application->vehicle_type_id === (int) $vehicleTypeId) {
            return $application;
        }

        $application->vehicle_type_id = $vehicleTypeId;
        $application->save();

        return $application->fresh();
    }

    protected function resolveVehicleTypeIdFromVehicle(Vehicle $vehicle): ?int
    {
        $brand = $this->normalizeVehicleText($vehicle->make);
        $model = $this->normalizeVehicleText($vehicle->model);
        $trim = $this->normalizeVehicleText($vehicle->trim);

        $matchingBrandModel = VehicleType::query()
            ->get()
            ->filter(function (VehicleType $type) use ($brand, $model): bool {
                return $this->normalizeVehicleText($type->brand) === $brand
                    && $this->normalizeVehicleText($type->model) === $model;
            })
            ->values();

        if ($matchingBrandModel->isEmpty()) {
            return null;
        }

        if ($trim !== '') {
            $exactVersion = $matchingBrandModel->first(
                fn (VehicleType $type): bool => $this->normalizeVehicleText($type->version) === $trim
            );

            if ($exactVersion) {
                return $exactVersion->getKey();
            }

            $partialVersion = $matchingBrandModel->first(function (VehicleType $type) use ($trim): bool {
                $version = $this->normalizeVehicleText($type->version);

                if ($version === '') {
                    return false;
                }

                return str_contains($trim, $version) || str_contains($version, $trim);
            });

            if ($partialVersion) {
                return $partialVersion->getKey();
            }
        }

        return $matchingBrandModel->first()?->getKey();
    }

    protected function normalizeVehicleText(?string $value): string
    {
        return Str::of((string) $value)
            ->replace("\xc2\xa0", ' ')
            ->squish()
            ->lower()
            ->value();
    }
}
