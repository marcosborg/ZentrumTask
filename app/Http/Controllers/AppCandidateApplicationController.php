<?php

namespace App\Http\Controllers;

use App\Models\CandidateApplication;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AppCandidateApplicationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $application = $this->resolveApplication($request->query('token'));

        return $this->jsonResponse([
            'application' => $application->toArray(),
            'vehicle_types' => VehicleType::query()
                ->orderBy('brand')
                ->orderBy('model')
                ->get()
                ->map(fn (VehicleType $type) => [
                    'id' => $type->id,
                    'brand' => $type->brand,
                    'model' => $type->model,
                    'version' => $type->version,
                    'weekly_rental_price' => $type->weekly_rental_price,
                ])
                ->values(),
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

        return $this->jsonResponse([
            'status' => 'ok',
            'token' => $application->token,
            'application' => $application->fresh()->toArray(),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $application = $this->findByToken($request->input('token'));

        $validated = $request->validate([
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
        ]);

        $documents = $application->documents ?? [];

        foreach (['document_id', 'driver_license', 'tvde_certificate', 'criminal_record'] as $docKey) {
            if (! $this->hasDocuments($documents[$docKey] ?? null)) {
                return $this->jsonResponse([
                    'message' => 'Falta enviar todos os documentos obrigatorios.',
                    'errors' => [
                        $docKey => ['Obrigatorio.'],
                    ],
                ], 422);
            }
        }

        $application->fill($validated);
        $application->status = 'submitted';
        $application->submitted_at = now();
        $application->last_ip = $request->ip();
        $application->legal_confirmed_at = now();
        $application->legal_ip = $request->ip();
        $application->legal_version = 'v1';
        $application->current_step = 'summary';
        $application->save();

        return $this->jsonResponse([
            'status' => 'submitted',
            'token' => $application->token,
            'application' => $application->fresh()->toArray(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $application = $this->findByToken($request->input('token'));

        $request->validate([
            'field' => ['required', Rule::in(['document_id', 'driver_license', 'tvde_certificate', 'criminal_record'])],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $field = (string) $request->input('field');
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

        return $this->jsonResponse([
            'status' => 'ok',
            'field' => $field,
            'document' => $document,
            'documents' => $documents[$field],
            'url' => Storage::disk('public')->url($path),
            'application' => $application->fresh()->toArray(),
        ]);
    }

    protected function resolveApplication(?string $token): CandidateApplication
    {
        if ($token) {
            $existing = CandidateApplication::query()->where('token', $token)->first();
            if ($existing) {
                return $existing;
            }
        }

        return CandidateApplication::query()->create([
            'status' => 'draft',
            'current_step' => 'welcome',
            'last_ip' => request()->ip(),
            'last_saved_at' => now(),
        ]);
    }

    protected function findByToken(?string $token): CandidateApplication
    {
        abort_if(! $token, 404);

        return CandidateApplication::query()->where('token', $token)->firstOrFail();
    }

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
            'legal' => $request->validate([
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

    protected function hasDocuments(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return array_is_list($value) ? count($value) > 0 : ! empty($value);
        }

        return false;
    }

    protected function jsonResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept',
        ]);
    }
}
