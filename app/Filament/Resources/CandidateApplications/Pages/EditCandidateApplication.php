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
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $documents = $data['documents'] ?? [];
        $keys = [
            'document_id',
            'driver_license',
            'tvde_certificate',
            'criminal_record',
        ];

        foreach ($keys as $key) {
            $documents[$key] = $this->normalizeDocumentPaths($documents[$key] ?? null);
        }

        $data['documents'] = $documents;

        return $data;
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

            $paths = $this->normalizeDocumentPaths($state);
            $existingItems = CandidateApplicationResource::normalizeDocumentItems($this->record, $existing[$key] ?? null);
            $existingByPath = [];

            foreach ($existingItems as $item) {
                $existingByPath[$item['path']] = $item;
            }

            $merged = [];

            foreach ($paths as $path) {
                $path = $this->normalizeDocumentPath($path);

                if ($path === null || $path === '') {
                    continue;
                }

                $existingItem = $existingByPath[$path] ?? null;
                $merged[] = array_filter([
                    'path' => $path,
                    'name' => $existingItem['name'] ?? basename($path),
                    'mime' => $existingItem['mime'] ?? $this->safeMimeType($path, null),
                    'size' => $existingItem['size'] ?? $this->safeFileSize($path, null),
                    'uploaded_at' => $existingItem['uploaded_at'] ?? now()->toIso8601String(),
                ], static fn ($value): bool => $value !== null);
            }

            $incoming[$key] = $merged;
        }

        return $incoming;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeDocumentPaths(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        if (! array_is_list($value)) {
            $path = $value['path'] ?? null;

            return is_string($path) && $path !== '' ? [$path] : [];
        }

        $paths = [];

        foreach ($value as $entry) {
            if (is_string($entry) && $entry !== '') {
                $paths[] = $entry;
            } elseif (is_array($entry)) {
                $path = $entry['path'] ?? null;
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    protected function normalizeDocumentPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $withDir = str_contains($path, '/') ? $path : "applications/{$this->record->token}/{$path}";

        if (Storage::disk('public')->exists($withDir)) {
            return $withDir;
        }

        $fallback = "applications/{$this->record->token}/".basename($path);

        if (Storage::disk('public')->exists($fallback)) {
            return $fallback;
        }

        return $withDir;
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
