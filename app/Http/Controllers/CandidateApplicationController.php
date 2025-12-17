<?php

namespace App\Http\Controllers;

use App\Models\CandidateApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CandidateApplicationController extends Controller
{
    public function show(Request $request)
    {
        $application = $this->resolveApplication($request);

        return view('candidatura.wizard', [
            'application' => $application,
            'uploadEndpoint' => route('candidatura.upload'),
            'saveEndpoint' => route('candidatura.save'),
            'submitEndpoint' => route('candidatura.submit'),
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
            'rgpd' => ['accepted'],
            'truth_declaration' => ['accepted'],
            'contact_authorization' => ['accepted'],
        ];

        $validated = $request->validate($rules);

        $documents = $application->documents ?? [];

        foreach (['document_id', 'driver_license', 'tvde_certificate', 'criminal_record'] as $docKey) {
            if (! isset($documents[$docKey])) {
                return response()->json([
                    'message' => 'Falta enviar todos os documentos obrigatorios.',
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
        $application->save();

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
        $documents[$field] = [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
        ];

        $application->documents = $documents;
        $application->status = $application->submitted_at ? 'submitted' : 'incomplete';
        $application->last_saved_at = now();
        $application->save();

        return response()->json([
            'status' => 'ok',
            'field' => $field,
            'document' => $documents[$field],
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    protected function resolveApplication(Request $request): CandidateApplication
    {
        if ($token = $request->session()->get('candidate_token')) {
            $existing = CandidateApplication::where('token', $token)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($token = $request->query('token')) {
            $existing = CandidateApplication::where('token', $token)->first();
            if ($existing) {
                $request->session()->put('candidate_token', $token);

                return $existing;
            }
        }

        $application = CandidateApplication::create([
            'status' => 'draft',
            'current_step' => 'welcome',
            'last_ip' => $request->ip(),
            'last_saved_at' => now(),
        ]);

        $request->session()->put('candidate_token', $application->token);

        return $application;
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
}
