<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
        {--disk= : Filesystem disk to store the backup}
        {--path= : Directory within the disk where the backup will be placed}
        {--filename= : Custom filename for the backup without extension}
        {--compress : Compress the backup using gzip}
        {--binary= : Custom mysqldump / mariadb-dump binary path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the configured database connection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = Config::get('database.default');
        $configuration = Config::get("database.connections.{$connection}");

        if ($configuration === null) {
            $this->error("Database connection [{$connection}] was not found.");

            return Command::FAILURE;
        }

        if (! in_array($configuration['driver'], ['mysql', 'mariadb'], true)) {
            $this->error('Only MySQL and MariaDB connections are supported.');

            return Command::FAILURE;
        }

        $disk = (string) ($this->option('disk') ?? Config::get('database.backup.disk', 'local'));
        $path = trim((string) ($this->option('path') ?? Config::get('database.backup.path', 'backups/database')), '/');
        $filename = (string) ($this->option('filename') ?? $this->buildFilename((string) $configuration['database']));
        $extension = $this->option('compress') ? 'sql.gz' : 'sql';
        $relativePath = $path === '' ? "{$filename}.{$extension}" : "{$path}/{$filename}.{$extension}";

        $storage = Storage::disk($disk);

        if ($path !== '' && ! $storage->exists($path)) {
            $storage->makeDirectory($path);
        }

        try {
            $binary = $this->resolveBinary(
                $configuration['driver'],
                $this->option('binary') ?? Config::get('database.backup.binary')
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $temporaryFile = $this->createTemporaryFile();

        $process = $this->buildDumpProcess($configuration, $temporaryFile, $binary);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->cleanupTemporaryFile($temporaryFile);
            $this->error(trim($process->getErrorOutput() ?: $process->getOutput()));

            return Command::FAILURE;
        }

        $contents = file_get_contents($temporaryFile);
        $this->cleanupTemporaryFile($temporaryFile);

        if ($contents === false) {
            $this->error('Could not read the temporary backup file.');

            return Command::FAILURE;
        }

        if ($this->option('compress')) {
            $contents = gzencode($contents);

            if ($contents === false) {
                $this->error('Could not compress the backup file.');

                return Command::FAILURE;
            }
        }

        if (! $storage->put($relativePath, $contents)) {
            $this->error('The backup file could not be saved.');

            return Command::FAILURE;
        }

        $this->info("Database backup saved to disk [{$disk}] at [{$relativePath}].");

        return Command::SUCCESS;
    }

    /**
     * @param  array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null,
     *     unix_socket?: string
     * }  $configuration
     */
    protected function buildDumpProcess(array $configuration, string $outputFile, string $binary): Process
    {
        $command = [
            $binary,
            '--protocol=TCP',
            '--host='.$configuration['host'],
            '--port='.(string) $configuration['port'],
            '--user='.$configuration['username'],
            '--password='.(string) ($configuration['password'] ?? ''),
            '--result-file='.$outputFile,
            '--single-transaction',
            '--routines',
            '--events',
            $configuration['database'],
        ];
        if (! empty($configuration['unix_socket'])) {
            $command[] = '--socket='.$configuration['unix_socket'];
        }

        $process = new Process($command);
        // Keep MYSQL_PWD too, for compatibility
        $process->setEnv([
            'MYSQL_PWD' => (string) ($configuration['password'] ?? ''),
        ]);
        $process->setTimeout(300);

        return $process;
    }

    protected function buildFilename(string $database): string
    {
        return sprintf(
            '%s-%s',
            $database,
            now()->format('Ymd_His')
        );
    }

    protected function createTemporaryFile(): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'db-backup-');

        if ($temporaryFile === false) {
            throw new RuntimeException('Unable to create a temporary file for the database backup.');
        }

        return $temporaryFile;
    }

    protected function cleanupTemporaryFile(?string $temporaryFile): void
    {
        if ($temporaryFile !== null && file_exists($temporaryFile)) {
            unlink($temporaryFile);
        }
    }

    protected function resolveBinary(string $driver, ?string $preferred): string
    {
        $default = $driver === 'mariadb' ? 'mariadb-dump' : 'mysqldump';
        $binary = $preferred !== null && $preferred !== '' ? $preferred : $default;

        if (str_contains($binary, '\\') || str_contains($binary, '/')) {
            if (! file_exists($binary)) {
                throw new RuntimeException("Dump binary [{$binary}] was not found. Configure DB_BACKUP_BINARY or use --binary with the full path.");
            }

            return $binary;
        }

        try {
            $probe = new Process([$binary, '--version']);
            $probe->setTimeout(5);
            $probe->run();
        } catch (RuntimeException $exception) {
            throw new RuntimeException("Dump binary [{$binary}] is not available on PATH. Install it or set DB_BACKUP_BINARY/--binary with the full path.");
        }

        if (! $probe->isSuccessful()) {
            throw new RuntimeException("Dump binary [{$binary}] is not available on PATH. Install it or set DB_BACKUP_BINARY/--binary with the full path.");
        }

        return $binary;
    }
}
