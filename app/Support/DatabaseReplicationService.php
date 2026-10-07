<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseReplicationService
{
    public function __construct(protected AwsDatabaseService $aws = new AwsDatabaseService) {}

    public function replicate(string $sourceMode, string $targetMode, ?\Closure $checkpoint = null): DatabaseReplicationResult
    {
        $backup = null;
        $temporaryPaths = [];
        $access = null;

        try {
            if (! config('database-management.local')) {
                throw new RuntimeException('A copia entre ambientes so pode ser executada no computador local.');
            }

            if (! in_array($sourceMode, ['sandbox', 'production'], true) || ! in_array($targetMode, ['sandbox', 'production'], true)
                || $sourceMode === $targetMode) {
                throw new RuntimeException('Escolha ambientes de origem e destino distintos.');
            }

            $source = $this->profile($sourceMode);
            $target = $this->profile($targetMode);

            if ($source['host'] === $target['host'] && (string) $source['port'] === (string) $target['port']
                && $source['database'] === $target['database']) {
                throw new RuntimeException('A origem e o destino apontam para a mesma base de dados.');
            }

            $access = $this->aws->access();
            $exists = $this->databaseExists($targetMode, $access);

            if ($exists) {
                $backup = $this->backup($targetMode, $access);
                $checkpoint?->__invoke($backup);
            }

            $dump = $this->temporaryPath();
            $temporaryPaths[] = $dump;
            $this->export($sourceMode, $dump, $exists, $access);
            $sql = $this->prepareImport($dump);
            $temporaryPaths[] = $sql;
            $this->import($targetMode, $sql, $access);

            return new DatabaseReplicationResult(true, "Dados copiados de {$sourceMode} para {$targetMode}.", 'Copia concluida', $backup);
        } catch (Throwable $exception) {
            return new DatabaseReplicationResult(false, $this->safeError($exception), 'Falha na copia', $backup);
        } finally {
            foreach ($temporaryPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if ($access !== null) {
                $this->aws->deleteKey($access['key']);
            }
        }
    }

    public function safeError(Throwable $exception): string
    {
        $message = $exception instanceof RuntimeException && ! $exception instanceof \Symfony\Component\Process\Exception\ProcessFailedException
            ? $exception->getMessage() : 'A operacao falhou. Verifique as ligacoes, permissoes e disponibilidade dos servicos.';

        foreach (['sandbox', 'production'] as $mode) {
            $password = (string) config("database.profiles.{$mode}.password", '');

            if ($password !== '') {
                $message = str_replace($password, '[oculto]', $message);
            }
        }

        return mb_substr($message, 0, 500);
    }

    /** @return array<string, mixed> */
    protected function profile(string $mode): array
    {
        $profile = config("database.profiles.{$mode}");

        if (! is_array($profile) || ! in_array($profile['driver'], ['mysql', 'mariadb'], true)
            || preg_match('/^[A-Za-z0-9_-]+$/D', (string) $profile['database']) !== 1) {
            throw new RuntimeException('Perfil MySQL/MariaDB invalido.');
        }

        return $profile;
    }

    public function probe(string $mode): void
    {
        if ($mode === 'production' && config('database-management.local')) {
            $this->aws->ensureTunnel();
        }

        $profile = $this->profile($mode);
        $process = $this->localQueryProcess($profile, 'SELECT 1', true);
        $this->runChecked($process);
    }

    public function createDump(string $mode, string $path): void
    {
        $this->profile($mode);
        $access = $mode === 'production' && config('database-management.local') ? $this->aws->access() : null;

        try {
            $this->export($mode, $path, false, $access, true);
        } finally {
            if ($access !== null) {
                $this->aws->deleteKey($access['key']);
            }
        }
    }

    /** @param array{host: string, user: string, key: string}|null $access
     * @return array{disk: string, path: string}
     */
    public function backup(string $mode, ?array $access = null): array
    {
        $this->profile($mode);

        if (! config('database-management.local') && $mode !== 'production') {
            throw new RuntimeException('Neste servidor so pode criar backups de producao.');
        }

        $path = $this->temporaryPath();
        $ownAccess = $mode === 'production' && config('database-management.local') && $access === null;

        try {
            if ($ownAccess) {
                $access = $this->aws->access();
            }

            $this->export($mode, $path, false, $access, true);
            $disk = (string) config('database.backup.disk');

            if (config('app.env') === 'production' && $disk !== 's3') {
                throw new RuntimeException('Configure DB_BACKUP_DISK=s3 para guardar backups AWS de forma persistente.');
            }

            $relativePath = trim((string) config('database.backup.path'), '/').'/'.$mode.'-'.now()->format('Ymd-His').'-'.\Illuminate\Support\Str::uuid().'.sql.gz';
            $stream = fopen($path, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Nao foi possivel abrir o backup.');
            }

            try {
                $saved = Storage::disk($disk)->put($relativePath, $stream, ['visibility' => 'private']);
            } finally {
                fclose($stream);
            }

            if (! $saved) {
                throw new RuntimeException('Nao foi possivel guardar o backup privado. A copia foi cancelada.');
            }

            return ['disk' => $disk, 'path' => $relativePath];
        } finally {
            if (is_file($path)) {
                unlink($path);
            }

            if ($ownAccess && $access !== null) {
                $this->aws->deleteKey($access['key']);
            }
        }
    }

    /** @param array{host: string, user: string, key: string}|null $access */
    protected function export(string $mode, string $path, bool $ignoreTransientTables, ?array $access, bool $complete = false): void
    {
        $profile = $this->profile($mode);

        if ($mode === 'production' && config('database-management.local')) {
            if ($access === null) {
                throw new RuntimeException('Acesso SSH indisponivel.');
            }

            $arguments = '--protocol=TCP --host="$db_host" --port="$db_port" --user="$db_user" '
                .'--no-tablespaces --single-transaction --routines --events --add-drop-table ';

            if (! $complete) {
                foreach ($this->ignoredReplicationTables($profile['database']) as $table) {
                    $arguments .= '--ignore-table='.$table.' ';
                }
            }

            $script = $this->aws->credentialsScript().'mysqldump '.$arguments.$profile['database'];

            if (! $complete && ! $ignoreTransientTables) {
                $script .= '; mysqldump --protocol=TCP --host="$db_host" --port="$db_port" --user="$db_user" '
                    .'--no-tablespaces --no-data '.$profile['database'].' sessions cache cache_locks jobs job_batches failed_jobs';
            }

            $process = $this->aws->remote($access, $this->aws->dockerScript('set -e; '.$script));
        } else {
            $process = $this->buildDumpProcess($profile, $this->resolveDumpBinary($profile['driver']), ! $complete);
        }

        $output = gzopen($path, 'wb6');
        $bytes = 0;

        if ($output === false) {
            throw new RuntimeException('Nao foi possivel criar o backup comprimido.');
        }

        try {
            $process->run(function (string $type, string $buffer) use ($output, &$bytes, $process): void {
                if ($type === Process::OUT) {
                    $written = gzwrite($output, $buffer);

                    if ($written !== strlen($buffer)) {
                        throw new RuntimeException('Falha ao gravar o backup comprimido.');
                    }

                    $bytes += $written;
                    $process->clearOutput();
                } else {
                    $process->clearErrorOutput();
                }
            });

            if ($process->isSuccessful() && $mode === 'sandbox' && ! $complete && ! $ignoreTransientTables) {
                $schema = $this->localProcess([$this->resolveDumpBinary($profile['driver']), '--protocol=TCP', '--host='.$profile['host'],
                    '--port='.(string) $profile['port'], '--user='.$profile['username'], '--no-tablespaces', '--no-data',
                    $profile['database'], 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], $profile);
                $schema->run(function (string $type, string $buffer) use ($output, $schema): void {
                    if ($type === Process::OUT && gzwrite($output, $buffer) !== strlen($buffer)) {
                        throw new RuntimeException('Falha ao guardar as estruturas operacionais.');
                    }

                    $schema->clearOutput();
                    $schema->clearErrorOutput();
                });

                if (! $schema->isSuccessful()) {
                    throw new RuntimeException('Falha ao exportar as estruturas operacionais.');
                }
            }
        } finally {
            $closed = gzclose($output);
        }

        if (! $process->isSuccessful() || ! $closed || $bytes === 0) {
            throw new RuntimeException('Exportacao da base de dados falhou ou devolveu um backup vazio. O destino nao foi alterado.');
        }
    }

    protected function prepareImport(string $dump): string
    {
        $path = $this->temporaryPath();
        $source = gzopen($dump, 'rb');
        $target = fopen($path, 'wb');

        if ($source === false || $target === false) {
            if (is_resource($source)) {
                gzclose($source);
            }

            if (is_resource($target)) {
                fclose($target);
            }

            unlink($path);
            throw new RuntimeException('Nao foi possivel preparar o ficheiro de importacao.');
        }

        $successful = false;

        try {
            $firstLine = gzgets($source);

            if ($firstLine === false) {
                throw new RuntimeException('O backup esta vazio.');
            }

            $firstLine = $this->sanitizeDumpContents($firstLine);

            if (fwrite($target, $firstLine) !== strlen($firstLine)) {
                throw new RuntimeException('Falha ao preparar a importacao.');
            }

            while (! gzeof($source)) {
                $buffer = gzread($source, 65536);

                if ($buffer === false || fwrite($target, $buffer) !== strlen($buffer)) {
                    throw new RuntimeException('Falha ao preparar a importacao.');
                }
            }
            $successful = true;
        } finally {
            gzclose($source);
            fclose($target);

            if (! $successful) {
                unlink($path);
            }
        }

        return $path;
    }

    /** @param array{host: string, user: string, key: string} $access */
    protected function import(string $mode, string $path, array $access): void
    {
        $profile = $this->profile($mode);
        $create = 'CREATE DATABASE IF NOT EXISTS `'.$profile['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

        if ($mode === 'production') {
            $script = $this->aws->credentialsScript()
                .'mysql --protocol=TCP --host="$db_host" --port="$db_port" --user="$db_user" --execute='.$this->quotePosix($create).'; '
                .'exec mysql --protocol=TCP --host="$db_host" --port="$db_port" --user="$db_user" '.$profile['database'];
            $process = $this->aws->remote($access, $this->productionImportScript($this->aws->dockerScript('set -e; '.$script)));
        } else {
            $this->runChecked($this->localQueryProcess($profile, $create));
            $process = $this->buildImportProcess($profile, $this->resolveImportBinary($profile['driver']));
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Ficheiro de importacao indisponivel.');
        }

        try {
            $process->setInput($stream);
            $this->runChecked($process);
        } finally {
            fclose($stream);
        }
    }

    protected function productionImportScript(string $import): string
    {
        $web = $this->aws->container();
        $worker = (string) config('database-management.worker_container');
        $scheduler = (string) config('database-management.scheduler_container');

        foreach ([$worker, $scheduler] as $container) {
            if (preg_match('/^[A-Za-z0-9_.-]+$/D', $container) !== 1) {
                throw new RuntimeException('Contentor de producao invalido.');
            }
        }

        return 'set -Eeuo pipefail; exec 9>/tmp/zentrum-db-management.lock; flock -n 9; stopped=""; raised=0; '
            .'restore() { result=$?; trap - EXIT; restore_failed=0; for name in $stopped; do sudo docker start "$name" >/dev/null || restore_failed=1; done; '
            .'if [ "$raised" = 1 ]; then sudo docker exec '.$web.' php artisan up --no-interaction >/dev/null || restore_failed=1; fi; '
            .'if [ "$restore_failed" = 1 ]; then exit 70; fi; exit "$result"; }; trap restore EXIT; '
            .'if ! sudo docker exec '.$web.' test -f storage/framework/down; then raised=1; '
            .'sudo docker exec '.$web.' php artisan down --retry=60 --no-interaction >/dev/null; fi; '
            .'for name in '.$worker.' '.$scheduler.'; do '
            .'state=$(sudo docker inspect --format "{{.State.Running}} {{.State.Paused}}" "$name"); '
            .'if [ "$state" = "true true" ]; then exit 73; fi; '
            .'if [ "$state" = "true false" ]; then stopped="$stopped $name"; sudo docker stop --time=180 "$name" >/dev/null; fi; done; '
            .'sleep 125; '.$import;
    }

    /** @param array{host: string, user: string, key: string} $access */
    protected function databaseExists(string $mode, array $access): bool
    {
        $profile = $this->profile($mode);
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".$profile['database']."'";

        if ($mode === 'production') {
            $process = $this->aws->remote($access, $this->aws->dockerScript($this->aws->credentialsScript()
                .'exec mysql --protocol=TCP --host="$db_host" --port="$db_port" --user="$db_user" --batch --skip-column-names --execute='.$this->quotePosix($query)));
        } else {
            $process = $this->localQueryProcess($profile, $query);
        }

        $this->runChecked($process);

        return trim($process->getOutput()) !== '';
    }

    /** @param array<string, mixed> $profile */
    protected function localQueryProcess(array $profile, string $query, bool $useDatabase = false): Process
    {
        $command = [$this->resolveImportBinary($profile['driver']), '--protocol=TCP', '--host='.$profile['host'],
            '--port='.(string) $profile['port'], '--user='.$profile['username'], '--batch', '--skip-column-names', '--execute='.$query];

        if ($useDatabase) {
            $command[] = '--database='.$profile['database'];
        }

        return $this->localProcess($command, $profile);
    }

    /** @param array<string, mixed> $configuration */
    protected function buildDumpProcess(array $configuration, string $binary, bool $ignoreTransientTables): Process
    {
        $command = [$binary, '--protocol=TCP', '--host='.$configuration['host'], '--port='.(string) $configuration['port'],
            '--user='.$configuration['username'], '--no-tablespaces', '--single-transaction', '--routines', '--events', '--add-drop-table'];

        if ($ignoreTransientTables) {
            foreach ($this->ignoredReplicationTables($configuration['database']) as $table) {
                $command[] = '--ignore-table='.$table;
            }
        }

        $command[] = $configuration['database'];

        return $this->localProcess($command, $configuration);
    }

    /** @param array<string, mixed> $configuration */
    protected function buildImportProcess(array $configuration, string $binary): Process
    {
        return $this->localProcess([$binary, '--protocol=TCP', '--host='.$configuration['host'],
            '--port='.(string) $configuration['port'], '--user='.$configuration['username'], '--database='.$configuration['database']], $configuration);
    }

    /** @param list<string> $command
     * @param  array<string, mixed>  $profile
     */
    protected function localProcess(array $command, array $profile): Process
    {
        $process = new Process($command, base_path());
        $process->setEnv($this->processEnvironment((string) ($profile['password'] ?? '')));
        $process->setTimeout(1500);

        return $process;
    }

    protected function runChecked(Process $process): void
    {
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Falha na operacao MySQL/SSH. Verifique ligacao, permissoes e disponibilidade. Se a importacao ja iniciou, utilize o backup do destino para recuperar.');
        }
    }

    protected function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zentrum-db-');

        if ($path === false) {
            throw new RuntimeException('Nao foi possivel criar um ficheiro temporario.');
        }

        return $path;
    }

    protected function sanitizeDumpContents(string $contents): string
    {
        return (string) preg_replace('/^\/\*M!999999\\\\- enable the sandbox mode \*\/\R?/', '', $contents, 1);
    }

    protected function quotePosix(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    /** @return list<string> */
    protected function ignoredReplicationTables(string $database): array
    {
        return array_map(fn (string $table): string => $database.'.'.$table, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs']);
    }

    /** @return array<string, string> */
    protected function processEnvironment(?string $password): array
    {
        $home = (string) (getenv('HOME') ?: getenv('USERPROFILE') ?: '');

        return array_filter(['MYSQL_PWD' => (string) $password, 'HOME' => $home, 'USERPROFILE' => (string) (getenv('USERPROFILE') ?: $home),
            'PATH' => (string) getenv('PATH'), 'SystemRoot' => (string) getenv('SystemRoot'), 'TEMP' => sys_get_temp_dir(), 'TMP' => sys_get_temp_dir(),
            'AWS_PROFILE' => (string) getenv('AWS_PROFILE')], static fn (string $value): bool => $value !== '');
    }

    protected function resolveDumpBinary(string $driver): string
    {
        return $this->resolveBinary((string) config('database.backup.binary'), $driver === 'mariadb' ? ['mariadb-dump', 'mysqldump'] : ['mysqldump', 'mariadb-dump']);
    }

    protected function resolveImportBinary(string $driver): string
    {
        return $this->resolveBinary((string) config('database.restore.binary'), $driver === 'mariadb' ? ['mariadb', 'mysql'] : ['mysql', 'mariadb']);
    }

    /** @param list<string> $candidates */
    protected function resolveBinary(string $preferred, array $candidates): string
    {
        foreach (array_filter([$preferred, ...$candidates]) as $candidate) {
            try {
                $process = new Process([$candidate, '--version']);
                $process->setTimeout(5);
                $process->run();

                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (Throwable) {
            }
        }

        throw new RuntimeException('Configure DB_BACKUP_BINARY e DB_RESTORE_BINARY com executaveis MySQL/MariaDB validos.');
    }
}
