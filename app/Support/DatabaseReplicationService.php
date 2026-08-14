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
            $dumpBinary = $this->usesLightsailDump($sourceMode)
                ? null
                : $this->resolveDumpBinary($sourceProfile['driver']);
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

        $dumpResult = $this->dumpDatabase(
            $sourceMode,
            $sourceProfile,
            $dumpBinary,
            $targetDatabaseExists
        );

        if (! $dumpResult['successful']) {
            return DatabaseReplicationResult::failure($dumpResult['message'], 'Erro a exportar base de dados');
        }

        $dumpContents = $this->sanitizeDumpContents($dumpResult['contents']);

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

    /**
     * @param  array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }  $configuration
     * @return array{successful: bool, contents: string, message: string}
     */
    protected function dumpDatabase(
        string $sourceMode,
        array $configuration,
        ?string $binary,
        bool $ignoreTransientTables
    ): array {
        if ($this->usesLightsailDump($sourceMode)) {
            return $this->dumpFromLightsail($configuration['database'], $ignoreTransientTables);
        }

        if ($binary === null) {
            return [
                'successful' => false,
                'contents' => '',
                'message' => 'Nao encontrei o binario de exportacao.',
            ];
        }

        $process = $this->buildDumpProcess($configuration, $binary, $ignoreTransientTables);
        $process->run();

        return $this->dumpProcessResult($process, $sourceMode);
    }

    /**
     * @return array{successful: bool, contents: string, message: string}
     */
    protected function dumpFromLightsail(string $database, bool $ignoreTransientTables): array
    {
        $configuration = Config::get('database.replication.production_dump', []);
        $region = (string) ($configuration['aws_region'] ?? '');
        $instance = (string) ($configuration['lightsail_instance'] ?? '');
        $container = (string) ($configuration['container'] ?? '');

        if ($region === '' || $instance === '' || $container === '') {
            return [
                'successful' => false,
                'contents' => '',
                'message' => 'Configure a regiao AWS, a instancia Lightsail e o contentor da producao no .env.',
            ];
        }

        $keyPath = null;

        try {
            $host = $this->resolveLightsailHost($configuration, $region, $instance);
            $keyPath = $this->downloadLightsailKey($configuration, $region);
            $process = $this->buildLightsailDumpProcess(
                $configuration,
                $host,
                $keyPath,
                $container,
                $database,
                $ignoreTransientTables
            );
            $process->run();

            $result = $this->dumpProcessResult($process, 'production');

            if (! $result['successful']) {
                return $result;
            }

            $contents = gzdecode($result['contents']);

            if ($contents === false) {
                return [
                    'successful' => false,
                    'contents' => '',
                    'message' => 'A exportacao recebida da producao nao e um arquivo gzip valido.',
                ];
            }

            return [
                'successful' => true,
                'contents' => $contents,
                'message' => '',
            ];
        } catch (Throwable $exception) {
            Log::error('Lightsail database dump crashed', [
                'exception' => $exception->getMessage(),
            ]);

            return [
                'successful' => false,
                'contents' => '',
                'message' => $exception->getMessage(),
            ];
        } finally {
            if ($keyPath !== null && is_file($keyPath)) {
                $this->deletePrivateKey($keyPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function resolveLightsailHost(array $configuration, string $region, string $instance): string
    {
        $configuredHost = (string) ($configuration['host'] ?? '');

        if ($configuredHost !== '') {
            return $configuredHost;
        }

        $process = new Process([
            (string) ($configuration['aws_binary'] ?? 'aws'),
            'lightsail',
            'get-instance',
            '--instance-name',
            $instance,
            '--region',
            $region,
            '--query',
            'instance.publicIpAddress',
            '--output',
            'text',
        ], base_path());
        $process->setEnv($this->processEnvironment(null));
        $process->setTimeout(60);
        $process->mustRun();

        $host = trim($process->getOutput());

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('A AWS nao devolveu um endereco valido para a instancia Lightsail.');
        }

        return $host;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function downloadLightsailKey(array $configuration, string $region): string
    {
        $process = new Process([
            (string) ($configuration['aws_binary'] ?? 'aws'),
            'lightsail',
            'download-default-key-pair',
            '--region',
            $region,
            '--query',
            'privateKeyBase64',
            '--output',
            'text',
        ], base_path());
        $process->setEnv($this->processEnvironment(null));
        $process->setTimeout(60);
        $process->mustRun();

        $privateKey = trim($process->getOutput());

        if (! str_contains($privateKey, 'BEGIN RSA PRIVATE KEY')) {
            $decoded = base64_decode($privateKey, true);

            if ($decoded !== false) {
                $privateKey = $decoded;
            }
        }

        if (! str_contains($privateKey, 'BEGIN RSA PRIVATE KEY')) {
            throw new RuntimeException('A AWS nao devolveu uma chave privada Lightsail valida.');
        }

        $keyPath = tempnam(sys_get_temp_dir(), 'zentrum-lightsail-');

        if ($keyPath === false || file_put_contents($keyPath, $privateKey.PHP_EOL) === false) {
            throw new RuntimeException('Nao consegui preparar a chave temporaria de acesso a producao.');
        }

        $this->securePrivateKey($keyPath);

        return $keyPath;
    }

    protected function securePrivateKey(string $keyPath): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            chmod($keyPath, 0600);

            return;
        }

        $username = $_SERVER['USERNAME'] ?? getenv('USERNAME') ?: '';

        if ($username === '') {
            throw new RuntimeException('Nao consegui identificar o utilizador Windows para proteger a chave SSH.');
        }

        $process = new Process([
            'icacls',
            $keyPath,
            '/inheritance:r',
            '/grant:r',
            $username.':(R)',
        ]);
        $process->setTimeout(30);
        $process->mustRun();
    }

    protected function deletePrivateKey(string $keyPath): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $username = $_SERVER['USERNAME'] ?? getenv('USERNAME') ?: '';

            if ($username !== '') {
                $process = new Process([
                    'icacls',
                    $keyPath,
                    '/grant:r',
                    $username.':(F)',
                ]);
                $process->setTimeout(30);
                $process->run();
            }
        }

        if (! unlink($keyPath)) {
            Log::warning('Temporary Lightsail key could not be deleted', [
                'path' => $keyPath,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function buildLightsailDumpProcess(
        array $configuration,
        string $host,
        string $keyPath,
        string $container,
        string $database,
        bool $ignoreTransientTables
    ): Process {
        foreach ([$container, $database] as $identifier) {
            if (preg_match('/^[A-Za-z0-9_.-]+$/', $identifier) !== 1) {
                throw new RuntimeException('A configuracao remota contem um identificador invalido.');
            }
        }

        $dumpArguments = [
            'mysqldump',
            '--host="$DB_HOST_PRODUCTION"',
            '--port="$DB_PORT_PRODUCTION"',
            '--user="$DB_USERNAME_PRODUCTION"',
            '--no-tablespaces',
            '--single-transaction',
            '--routines',
            '--events',
            '--add-drop-table',
            $database,
        ];

        if ($ignoreTransientTables) {
            foreach ($this->ignoredReplicationTables($database) as $table) {
                $dumpArguments[] = '--ignore-table='.$table;
            }
        }

        $script = 'MYSQL_PWD="$DB_PASSWORD_PRODUCTION" '.implode(' ', $dumpArguments).' | gzip -c';
        $remoteCommand = 'sudo docker exec '.$container.' sh -lc '.$this->quotePosix($script);
        $sshUser = (string) ($configuration['ssh_user'] ?? 'ubuntu');

        if (preg_match('/^[A-Za-z0-9_.-]+$/', $sshUser) !== 1) {
            throw new RuntimeException('O utilizador SSH configurado e invalido.');
        }

        $process = new Process([
            (string) ($configuration['ssh_binary'] ?? 'ssh'),
            '-n',
            '-i',
            $keyPath,
            '-o',
            'BatchMode=yes',
            '-o',
            'ConnectTimeout=20',
            '-o',
            'StrictHostKeyChecking=accept-new',
            $sshUser.'@'.$host,
            $remoteCommand,
        ], base_path());
        $process->setEnv($this->processEnvironment(null));
        $process->setTimeout(300);

        return $process;
    }

    /**
     * @return array{successful: bool, contents: string, message: string}
     */
    protected function dumpProcessResult(Process $process, string $sourceMode): array
    {
        if ($process->isSuccessful()) {
            return [
                'successful' => true,
                'contents' => $process->getOutput(),
                'message' => '',
            ];
        }

        Log::error('Database dump failed', [
            'source' => $sourceMode,
            'command' => $process->getCommandLine(),
            'exit_code' => $process->getExitCode(),
            'error_output' => $process->getErrorOutput(),
            'output_bytes' => strlen($process->getOutput()),
        ]);

        return [
            'successful' => false,
            'contents' => '',
            'message' => trim($process->getErrorOutput() ?: $process->getOutput()),
        ];
    }

    protected function usesLightsailDump(string $sourceMode): bool
    {
        return $sourceMode === 'production'
            && Config::get('database.replication.production_dump.strategy') === 'lightsail';
    }

    protected function sanitizeDumpContents(string $contents): string
    {
        return (string) preg_replace('/^\/\*M!999999\\\\- enable the sandbox mode \*\/\R?/', '', $contents, 1);
    }

    protected function quotePosix(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
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
            'USERPROFILE' => $_SERVER['USERPROFILE'] ?? getenv('USERPROFILE') ?: '',
            'APPDATA' => $_SERVER['APPDATA'] ?? getenv('APPDATA') ?: '',
            'LOCALAPPDATA' => $_SERVER['LOCALAPPDATA'] ?? getenv('LOCALAPPDATA') ?: '',
            'AWS_PROFILE' => $_SERVER['AWS_PROFILE'] ?? getenv('AWS_PROFILE') ?: '',
            'AWS_DEFAULT_PROFILE' => $_SERVER['AWS_DEFAULT_PROFILE'] ?? getenv('AWS_DEFAULT_PROFILE') ?: '',
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
