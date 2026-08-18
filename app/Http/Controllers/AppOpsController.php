<?php

namespace App\Http\Controllers;

use App\Models\CandidateApplication;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppOpsController extends AppApiController
{
    public function overview(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $today = Carbon::today();
        $expiring60 = $today->copy()->addDays(60);

        $applications = CandidateApplication::query()
            ->latest('created_at')
            ->limit(50)
            ->get();

        $drivers = Driver::query()
            ->with(['currentAllocation.vehicle', 'company'])
            ->withExists([
                'billingProfiles as has_active_billing_profile' => fn (Builder $query): Builder => $query->active(),
            ])
            ->orderBy('name')
            ->limit(50)
            ->get();

        $vehicles = Vehicle::query()
            ->with(['currentAllocation.driver'])
            ->withCount([
                'documents as expired_documents_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', $today),
                'documents as expiring_60_documents_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [$today, $expiring60]),
            ])
            ->orderBy('license_plate')
            ->limit(50)
            ->get();

        return $this->corsJson([
            'candidate_applications' => $applications->map(fn (CandidateApplication $application): array => [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'status' => $application->status,
                'status_label' => match ($application->status) {
                    'submitted' => 'Submetida',
                    'incomplete' => 'Incompleta',
                    'converted' => 'Convertida',
                    default => 'Rascunho',
                },
                'current_step' => $application->current_step,
                'submitted_at' => optional($application->submitted_at)?->toIso8601String(),
                'submitted_at_label' => optional($application->submitted_at)?->format('d/m H:i'),
                'created_at' => optional($application->created_at)?->toIso8601String(),
                'created_at_label' => optional($application->created_at)?->format('d/m H:i'),
            ])->values()->all(),
            'drivers' => $drivers->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'bolt_driver_code' => $driver->bolt_driver_code,
                'uber_driver_code' => $driver->uber_driver_code,
                'current_vehicle_license_plate' => $driver->currentAllocation?->vehicle?->license_plate,
                'has_active_billing_profile' => (bool) ($driver->has_active_billing_profile ?? false),
                'company_name' => $driver->company?->name,
            ])->values()->all(),
            'vehicles' => $vehicles->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
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
                'source' => $vehicle->source,
                'source_label' => match ($vehicle->source) {
                    'tvde' => 'TVDE',
                    'outsource' => 'Outsource',
                    'company' => 'Company',
                    'private' => 'Private',
                    default => (string) $vehicle->source,
                },
                'current_driver_name' => $vehicle->currentAllocation?->driver?->name,
                'expired_documents_count' => (int) ($vehicle->expired_documents_count ?? 0),
                'expiring_60_documents_count' => (int) ($vehicle->expiring_60_documents_count ?? 0),
            ])->values()->all(),
        ]);
    }
}
