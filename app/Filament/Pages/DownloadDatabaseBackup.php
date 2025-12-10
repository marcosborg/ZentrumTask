<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
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
                ->action(fn (): ?Response => $this->downloadLatest()),
            Action::make('generate')
                ->label('Gerar novo backup')
                ->icon(Heroicon::OutlinedServer)
                ->action(fn () => $this->generateBackup())
                ->requiresConfirmation()
                ->modalHeading('Gerar novo backup da base de dados')
                ->modalDescription('Esta ação executa php artisan db:backup e cria um novo ficheiro.'),
        ];
    }

    protected function downloadLatest(): ?Response
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

    protected function generateBackup(): void
    {
        $diskName = (string) Config::get('database.backup.disk', 'local');
        $path = trim((string) Config::get('database.backup.path', 'backups/database'), '/');

        try {
            $exitCode = Artisan::call('db:backup');
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                Notification::make()
                    ->danger()
                    ->title('Erro ao criar backup')
                    ->body($output !== '' ? $output : 'O comando db:backup terminou com erro.')
                    ->send();

                return;
            }

            Notification::make()
                ->success()
                ->title('Backup criado com sucesso')
                ->body("Novo ficheiro guardado em [{$diskName}] em {$path}.")
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Erro ao criar backup')
                ->body($e->getMessage())
                ->send();
        }
    }
}
