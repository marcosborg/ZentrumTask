<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
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
            Action::make('productionToSandbox')
                ->label('Copiar producao -> sandbox')
                ->color('danger')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->requiresConfirmation()
                ->modalHeading('Copiar base de dados externa para interna')
                ->modalDescription('Substitui totalmente a base de dados sandbox pelos dados da base externa.')
                ->action(fn () => $this->replicateDatabase('production', 'sandbox')),
            Action::make('sandboxToProduction')
                ->label('Copiar sandbox -> producao')
                ->color('warning')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->requiresConfirmation()
                ->modalHeading('Copiar base de dados interna para externa')
                ->modalDescription('Substitui totalmente a base de dados externa pelos dados da base sandbox.')
                ->action(fn () => $this->replicateDatabase('sandbox', 'production')),
            Action::make('toggleMode')
                ->label(fn (): string => $this->toggleLabel())
                ->color('success')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->requiresConfirmation()
                ->modalHeading('Alternar entre sandbox e producao')
                ->modalDescription('Atualiza a variavel DB_MODE no ficheiro .env e recarrega a configuracao.')
                ->action(fn () => $this->toggleDatabaseMode()),
        ];
    }

    protected function replicateDatabase(string $sourceMode, string $targetMode): void
    {
        $sourceProfile = $this->databaseProfile($sourceMode);
        $targetProfile = $this->databaseProfile($targetMode);

        if ($sourceProfile === null || $targetProfile === null) {
            Notification::make()
                ->danger()
                ->title('Configuracao em falta')
                ->body("Nao encontrei perfis para {$sourceMode} ou {$targetMode}. Atualize o .env.")
                ->send();

            return;
        }

        if (! in_array($sourceProfile['driver'], ['mysql', 'mariadb'], true) || ! in_array($targetProfile['driver'], ['mysql', 'mariadb'], true)) {
            Notification::make()
                ->danger()
                ->title('Driver nao suportado')
                ->body('A copia so suporta MySQL/MariaDB.')
                ->send();

            return;
        }

        $dumpBinary = $this->resolveDumpBinary($sourceProfile['driver']);
        $importBinary = $this->resolveImportBinary($targetProfile['driver']);

        $dumpProcess = $this->buildDumpProcess($sourceProfile, $dumpBinary);
        $dumpProcess->run();

        if (! $dumpProcess->isSuccessful()) {
            Log::error('Database dump failed', [
                'source' => $sourceMode,
                'command' => $dumpProcess->getCommandLine(),
                'exit_code' => $dumpProcess->getExitCode(),
                'error_output' => $dumpProcess->getErrorOutput(),
                'output' => $dumpProcess->getOutput(),
            ]);

            Notification::make()
                ->danger()
                ->title('Erro a exportar base de dados')
                ->body(trim($dumpProcess->getErrorOutput() ?: $dumpProcess->getOutput()))
                ->send();

            return;
        }

        $dumpContents = $dumpProcess->getOutput();

        if ($dumpContents === '') {
            Notification::make()
                ->danger()
                ->title('Backup vazio')
                ->body('A exportacao nao devolveu dados. Verifique a ligacao de origem.')
                ->send();

            return;
        }

        if (! $this->ensureDatabaseExists($targetProfile, $importBinary)) {
            return;
        }

        $importProcess = $this->buildImportProcess($targetProfile, $importBinary);
        $importProcess->setInput($dumpContents);
        try {
            $importProcess->run();
        } catch (Throwable $exception) {
            Log::error('Database import crashed', [
                'target' => $targetMode,
                'command' => $importProcess->getCommandLine(),
                'exception' => $exception->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('Erro a importar base de dados')
                ->body("A importacao foi interrompida: {$exception->getMessage()}")
                ->send();

            return;
        }

        if (! $importProcess->isSuccessful()) {
            Log::error('Database import failed', [
                'target' => $targetMode,
                'command' => $importProcess->getCommandLine(),
                'exit_code' => $importProcess->getExitCode(),
                'error_output' => $importProcess->getErrorOutput(),
                'output' => $importProcess->getOutput(),
            ]);

            Notification::make()
                ->danger()
                ->title('Erro a importar base de dados')
                ->body(trim($importProcess->getErrorOutput() ?: $importProcess->getOutput()))
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Copia concluida')
            ->body("Dados copiados de {$sourceMode} para {$targetMode}.")
            ->send();
    }

    /**
     * @param  array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }  $configuration
     */
    protected function buildDumpProcess(array $configuration, string $binary): Process
    {
        $command = [
            $binary,
            '--protocol=TCP',
            '--host='.$configuration['host'],
            '--port='.(string) $configuration['port'],
            '--user='.$configuration['username'],
            '--password='.(string) ($configuration['password'] ?? ''),
            '--no-tablespaces',
            '--single-transaction',
            '--routines',
            '--events',
            '--add-drop-table',
            $configuration['database'],
        ];

        $process = new Process($command, base_path());
        $process->setEnv($this->processEnvironment((string) ($configuration['password'] ?? '')));
        $process->setTimeout(300);

        return $process;
    }

    /**
     * @param  array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }  $configuration
     */
    protected function buildImportProcess(array $configuration, string $binary): Process
    {
        $command = [
            $binary,
            '--protocol=TCP',
            '--host='.$configuration['host'],
            '--port='.(string) $configuration['port'],
            '--user='.$configuration['username'],
            '--password='.(string) ($configuration['password'] ?? ''),
            '--database='.$configuration['database'],
        ];

        $process = new Process($command, base_path());
        $process->setEnv($this->processEnvironment((string) ($configuration['password'] ?? '')));
        $process->setTimeout(300);

        return $process;
    }

    /**
     * @param  array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }  $configuration
     */
    protected function ensureDatabaseExists(array $configuration, string $binary): bool
    {
        $command = [
            $binary,
            '--protocol=TCP',
            '--host='.$configuration['host'],
            '--port='.(string) $configuration['port'],
            '--user='.$configuration['username'],
            '--password='.(string) ($configuration['password'] ?? ''),
            '--execute=CREATE DATABASE IF NOT EXISTS `'.$configuration['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        ];

        $process = new Process($command, base_path());
        $process->setEnv($this->processEnvironment((string) ($configuration['password'] ?? '')));
        $process->setTimeout(60);
        $process->run();

        if ($process->isSuccessful()) {
            return true;
        }

        Log::error('Database create failed', [
            'database' => $configuration['database'],
            'command' => $process->getCommandLine(),
            'exit_code' => $process->getExitCode(),
            'error_output' => $process->getErrorOutput(),
            'output' => $process->getOutput(),
        ]);

        Notification::make()
            ->danger()
            ->title('Erro a preparar base de dados')
            ->body('Nao consegui preparar a base de dados de destino: '.trim($process->getErrorOutput() ?: $process->getOutput()))
            ->send();

        return false;
    }

    protected function toggleLabel(): string
    {
        $currentMode = (string) Config::get('database.mode', 'sandbox');
        $targetMode = $currentMode === 'production' ? 'sandbox' : 'production';

        return "Alternar para {$targetMode}";
    }

    protected function toggleDatabaseMode(): void
    {
        $currentMode = (string) Config::get('database.mode', 'sandbox');
        $targetMode = $currentMode === 'production' ? 'sandbox' : 'production';

        if (! $this->persistEnvValue('DB_MODE', $targetMode)) {
            Notification::make()
                ->danger()
                ->title('Nao foi possivel alterar DB_MODE')
                ->body('Falha ao escrever no ficheiro .env.')
                ->send();

            return;
        }

        Config::set('database.mode', $targetMode);

        $profile = $this->databaseProfile($targetMode);

        if ($profile !== null) {
            $this->applyProfileToConnections($profile);
            $this->refreshDatabaseConnections();
        }

        Notification::make()
            ->success()
            ->title('Modo alterado')
            ->body("DB_MODE atualizado para {$targetMode}.")
            ->send();
    }

    protected function persistEnvValue(string $key, string $value): bool
    {
        $envPath = base_path('.env');

        $contents = file_exists($envPath) ? file_get_contents($envPath) : '';

        if ($contents === false) {
            return false;
        }

        $line = "{$key}={$value}";
        $pattern = '/^'.$key.'=.*/m';

        if (preg_match($pattern, $contents) === 1) {
            $contents = (string) preg_replace($pattern, $line, $contents);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        return file_put_contents($envPath, $contents) !== false;
    }

    /**
     * @return array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }|null
     */
    protected function databaseProfile(string $mode): ?array
    {
        $profiles = Config::get('database.profiles', []);

        return $profiles[$mode] ?? null;
    }

    /**
     * @param  array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }  $profile
     */
    protected function applyProfileToConnections(array $profile): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            Config::set("database.connections.{$connection}.host", $profile['host']);
            Config::set("database.connections.{$connection}.port", $profile['port']);
            Config::set("database.connections.{$connection}.database", $profile['database']);
            Config::set("database.connections.{$connection}.username", $profile['username']);
            Config::set("database.connections.{$connection}.password", $profile['password']);
        }
    }

    protected function refreshDatabaseConnections(): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            DB::purge($connection);
            DB::reconnect($connection);
        }
    }

    protected function resolveDumpBinary(string $driver): string
    {
        $preferred = (string) Config::get('database.backup.binary', '');

        if ($preferred !== '') {
            return $preferred;
        }

        return $driver === 'mariadb' ? 'mariadb-dump' : 'mysqldump';
    }

    protected function resolveImportBinary(string $driver): string
    {
        $preferred = (string) Config::get('database.restore.binary', '');

        if ($preferred !== '') {
            return $preferred;
        }

        return $driver === 'mariadb' ? 'mariadb' : 'mysql';
    }

    /**
     * Build a minimal environment so mysqldump/mysql work under Apache on Windows.
     *
     * @return array<string, string>
     */
    protected function processEnvironment(?string $password): array
    {
        $systemRoot = $_SERVER['SystemRoot'] ?? getenv('SystemRoot') ?: '';
        $path = $_SERVER['PATH'] ?? getenv('PATH') ?: '';
        $temp = sys_get_temp_dir();
        $env = [
            'SystemRoot' => $systemRoot,
            'WINDIR' => $_SERVER['WINDIR'] ?? getenv('WINDIR') ?: $systemRoot,
            'PATH' => $path,
            'TEMP' => $temp,
            'TMP' => $temp,
        ];

        $filtered = array_filter($env, static fn (string $value): bool => $value !== '');

        $filtered['MYSQL_PWD'] = (string) ($password ?? '');

        return $filtered;
    }
}
