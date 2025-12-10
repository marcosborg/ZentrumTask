<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class DownloadDatabaseBackup extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static UnitEnum|string|null $navigationGroup = 'Administracao';

    protected static ?string $navigationLabel = 'Backup da Base de Dados';

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.download-database-backup';

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Descarregar último backup')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): ?StreamedResponse => $this->downloadLatest()),
        ];
    }

    protected function downloadLatest(): ?StreamedResponse
    {
        $diskName = (string) Config::get('database.backup.disk', 'local');
        $path = trim((string) Config::get('database.backup.path', 'backups/database'), '/');
        $disk = Storage::disk($diskName);

        $latest = $this->latestBackupPath($disk, $path);

        if ($latest === null) {
            Notification::make()
                ->danger()
                ->title('Nenhum backup encontrado')
                ->body('Crie um backup primeiro com o comando php artisan db:backup.')
                ->send();

            return null;
        }

        return $disk->download($latest, basename($latest));
    }

    protected function latestBackupPath(Filesystem $disk, string $directory): ?string
    {
        if (! $disk->exists($directory)) {
            return null;
        }

        $files = $disk->files($directory);
        $backupFiles = array_filter($files, static function (string $file): bool {
            return str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz');
        });

        if ($backupFiles === []) {
            return null;
        }

        $sorted = collect($backupFiles)
            ->mapWithKeys(function (string $file) use ($disk) {
                return [$file => $disk->lastModified($file)];
            })
            ->sortDesc();

        return $sorted->keys()->first();
    }
}
