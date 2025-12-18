<?php

namespace App\Filament\Resources\CandidateApplications\Pages;

use App\Filament\Resources\CandidateApplications\CandidateApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditCandidateApplication extends EditRecord
{
    protected static string $resource = CandidateApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingDocuments = $this->record->documents ?? [];

        $data['documents'] = $this->mergeDocuments(
            $existingDocuments,
            $data['documents'] ?? []
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergeDocuments(array $existing, array $incoming): array
    {
        $keys = [
            'document_id',
            'driver_license',
            'tvde_certificate',
            'criminal_record',
        ];

        foreach ($keys as $key) {
            $state = $incoming[$key] ?? null;

            if ($state === null || $state === '') {
                if (isset($existing[$key])) {
                    $incoming[$key] = $existing[$key];
                }

                continue;
            }

            $path = is_array($state) ? ($state['path'] ?? null) : (string) $state;

            if ($path === null || $path === '') {
                $incoming[$key] = $existing[$key] ?? null;

                continue;
            }

            $meta = [
                'path' => $path,
                'name' => basename($path),
                'mime' => $this->safeMimeType($path, $existing[$key]['mime'] ?? null),
                'size' => $this->safeFileSize($path, $existing[$key]['size'] ?? null),
                'uploaded_at' => now()->toIso8601String(),
            ];

            $incoming[$key] = array_filter(
                $meta + ($existing[$key] ?? []),
                static fn ($value): bool => $value !== null
            );
        }

        return $incoming;
    }

    protected function safeMimeType(string $path, ?string $fallback): ?string
    {
        try {
            return Storage::disk('public')->mimeType($path) ?: $fallback;
        } catch (\Throwable) { // @phpstan-ignore-line
            return $fallback;
        }
    }

    protected function safeFileSize(string $path, ?int $fallback): ?int
    {
        try {
            return Storage::disk('public')->size($path);
        } catch (\Throwable) { // @phpstan-ignore-line
            return $fallback;
        }
    }
}
