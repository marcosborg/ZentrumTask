<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseReplicationService
{
    public function replicate(string $sourceMode, string $targetMode): DatabaseReplicationResult
    {
        $sourceProfile = $this->databaseProfile($sourceMode);
        $targetProfile = $this->databaseProfile($targetMode);

        if ($sourceProfile === null || $targetProfile === null) {
            return DatabaseReplicationResult::failure(
                "Nao encontrei perfis para {$sourceMode} ou {$targetMode}. Atualize o .env."
            );
        }

        if (! in_array($sourceProfile['driver'], ['mysql', 'mariadb'], true) || ! in_array($targetProfile['driver'], ['mysql', 'mariadb'], true)) {
            return DatabaseReplicationResult::failure('A copia so suporta MySQL/MariaDB.');
        }

        try {
            $dumpBinary = $this->resolveDumpBinary($sourceProfile['driver']);
            $importBinary = $this->resolveImportBinary($targetProfile['driver']);
        } catch (RuntimeException $exception) {
            return DatabaseReplicationResult::failure(
                $exception->getMessage(),
                'Binario nao encontrado'
            );
        }

        $targetDatabaseExists = $this->databaseExists($targetProfile, $importBinary);

        if ($targetDatabaseExists === null) {
            return DatabaseReplicationResult::failure(
                'Nao consegui verificar se a base de dados de destino ja existe.',
                'Erro a verificar base de dados'
            );
        }

        $dumpProcess = $this->buildDumpProcess($sourceProfile, $dumpBinary, $targetDatabaseExists);
        $dumpProcess->run();

        if (! $dumpProcess->isSuccessful()) {
            Log::error('Database dump failed', [
                'source' => $sourceMode,
                'command' => $dumpProcess->getCommandLine(),
                'exit_code' => $dumpProcess->getExitCode(),
                'error_output' => $dumpProcess->getErrorOutput(),
                'output' => $dumpProcess->getOutput(),
            ]);

            return DatabaseReplicationResult::failure(
                trim($dumpProcess->getErrorOutput() ?: $dumpProcess->getOutput()),
                'Erro a exportar base de dados'
            );
        }

        $dumpContents = $dumpProcess->getOutput();

        if ($dumpContents === '') {
            return DatabaseReplicationResult::failure(
                'A exportacao nao devolveu dados. Verifique a ligacao de origem.',
                'Backup vazio'
            );
        }

        $prepared = $this->ensureDatabaseExists($targetProfile, $importBinary);

        if (! $prepared->successful) {
            return $prepared;
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

            return DatabaseReplicationResult::failure(
                "A importacao foi interrompida: {$exception->getMessage()}",
                'Erro a importar base de dados'
            );
        }

        if (! $importProcess->isSuccessful()) {
            Log::error('Database import failed', [
                'target' => $targetMode,
                'command' => $importProcess->getCommandLine(),
                'exit_code' => $importProcess->getExitCode(),
                'error_output' => $importProcess->getErrorOutput(),
                'output' => $importProcess->getOutput(),
            ]);

            return DatabaseReplicationResult::failure(
                trim($importProcess->getErrorOutput() ?: $importProcess->getOutput()),
                'Erro a importar base de dados'
            );
        }

        return DatabaseReplicationResult::success("Dados copiados de {$sourceMode} para {$targetMode}.");
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
    protected function buildDumpProcess(array $configuration, string $binary, bool $ignoreTransientTables): Process
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

        if ($ignoreTransientTables) {
            foreach ($this->ignoredReplicationTables($configuration['database']) as $table) {
                $command[] = '--ignore-table='.$table;
            }
        }

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
    protected function ensureDatabaseExists(array $configuration, string $binary): DatabaseReplicationResult
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
            return DatabaseReplicationResult::success('Database preparada.');
        }

        Log::error('Database create failed', [
            'database' => $configuration['database'],
            'command' => $process->getCommandLine(),
            'exit_code' => $process->getExitCode(),
            'error_output' => $process->getErrorOutput(),
            'output' => $process->getOutput(),
        ]);

        return DatabaseReplicationResult::failure(
            'Nao consegui preparar a base de dados de destino: '.trim($process->getErrorOutput() ?: $process->getOutput()),
            'Erro a preparar base de dados'
        );
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
    protected function databaseExists(array $configuration, string $binary): ?bool
    {
        $command = [
            $binary,
            '--protocol=TCP',
            '--host='.$configuration['host'],
            '--port='.(string) $configuration['port'],
            '--user='.$configuration['username'],
            '--password='.(string) ($configuration['password'] ?? ''),
            '--batch',
            '--skip-column-names',
            '--execute=SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''.$this->escapeSqlString($configuration['database']).'\'',
        ];

        $process = new Process($command, base_path());
        $process->setEnv($this->processEnvironment((string) ($configuration['password'] ?? '')));
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('Database existence check failed', [
                'database' => $configuration['database'],
                'command' => $process->getCommandLine(),
                'exit_code' => $process->getExitCode(),
                'error_output' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);

            return null;
        }

        return trim($process->getOutput()) !== '';
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

    protected function resolveDumpBinary(string $driver): string
    {
        $preferred = (string) Config::get('database.backup.binary', '');
        $candidates = $driver === 'mariadb'
            ? ['mariadb-dump', 'mysqldump']
            : ['mysqldump', 'mariadb-dump'];

        return $this->resolveBinary($preferred, $candidates);
    }

    protected function resolveImportBinary(string $driver): string
    {
        $preferred = (string) Config::get('database.restore.binary', '');
        $candidates = $driver === 'mariadb'
            ? ['mariadb', 'mysql']
            : ['mysql', 'mariadb'];

        return $this->resolveBinary($preferred, $candidates);
    }

    protected function escapeSqlString(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }

    /**
     * @return array<int, string>
     */
    protected function ignoredReplicationTables(string $database): array
    {
        $tables = [
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ];

        return array_map(
            fn (string $table): string => "{$database}.{$table}",
            $tables
        );
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

    /**
     * @param  array<int, string>  $fallbacks
     */
    protected function resolveBinary(string $preferred, array $fallbacks): string
    {
        $candidates = [];

        if ($preferred !== '') {
            $candidates[] = $preferred;
        }

        foreach ($fallbacks as $fallback) {
            $candidates[] = $fallback;
        }

        foreach ($this->commonMampBinaryPaths($fallbacks) as $candidate) {
            $candidates[] = $candidate;
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (! $this->looksUsableBinary($candidate)) {
                continue;
            }

            return $candidate;
        }

        throw new RuntimeException('Nao encontrei um binario mysql/mysqldump executavel. Atualize DB_BACKUP_BINARY/DB_RESTORE_BINARY no .env.');
    }

    protected function looksUsableBinary(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        if ($this->isWindowsPath($candidate) && DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }

        if (str_contains($candidate, '/') || str_contains($candidate, '\\')) {
            return is_file($candidate) && is_executable($candidate);
        }

        $probe = new Process(['sh', '-lc', 'command -v '.escapeshellarg($candidate)]);
        $probe->setTimeout(5);
        $probe->run();

        return $probe->isSuccessful();
    }

    protected function isWindowsPath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @param  array<int, string>  $fallbacks
     * @return array<int, string>
     */
    protected function commonMampBinaryPaths(array $fallbacks): array
    {
        $paths = [];

        foreach ($fallbacks as $binary) {
            $paths[] = "/Applications/MAMP/Library/bin/mysql80/bin/{$binary}";
            $paths[] = "/Applications/MAMP/Library/bin/mysql57/bin/{$binary}";
        }

        return $paths;
    }
}
