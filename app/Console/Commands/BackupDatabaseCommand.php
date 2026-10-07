<?php

namespace App\Console\Commands;

use App\Support\DatabaseManagementService;
use App\Support\DatabaseReplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
        {--disk= : Filesystem disk to store the backup}
        {--path= : Directory within the disk where the backup will be placed}
        {--filename= : Custom filename for the backup without extension}
        {--compress : Compress the backup using gzip}
        {--binary= : Custom mysqldump / mariadb-dump binary path}';

    protected $description = 'Create a private backup of the active database, using SSH for local AWS access';

    public function handle(DatabaseReplicationService $replication, DatabaseManagementService $management): int
    {
        try {
            return $management->exclusive(function () use ($replication): int {
                $mode = (string) config('database.mode');
                $disk = (string) ($this->option('disk') ?? config('database.backup.disk'));
                $path = trim((string) ($this->option('path') ?? config('database.backup.path')), '/');
                $filename = (string) ($this->option('filename') ?? $mode.'-'.now()->format('Ymd_His'));

                if (preg_match('/^[A-Za-z0-9_-]+$/D', $filename) !== 1) {
                    throw new RuntimeException('Nome de ficheiro de backup invalido.');
                }

                if (! config('database-management.local') && $disk !== 's3') {
                    throw new RuntimeException('Os backups AWS devem ser guardados no disco privado s3.');
                }

                if ($this->option('binary')) {
                    config(['database.backup.binary' => $this->option('binary')]);
                }

                $temporary = tempnam(sys_get_temp_dir(), 'zentrum-backup-');

                if ($temporary === false) {
                    throw new RuntimeException('Nao foi possivel criar o ficheiro temporario de backup.');
                }

                $stream = null;

                try {
                    $replication->createDump($mode, $temporary);
                    $compressed = (bool) $this->option('compress');
                    $stream = fopen($compressed ? $temporary : 'compress.zlib://'.$temporary, 'rb');

                    if ($stream === false) {
                        throw new RuntimeException('Nao foi possivel abrir o backup.');
                    }

                    $relative = ($path === '' ? '' : $path.'/').$filename.($compressed ? '.sql.gz' : '.sql');

                    if (! Storage::disk($disk)->put($relative, $stream, ['visibility' => 'private'])) {
                        throw new RuntimeException('Nao foi possivel guardar o backup privado.');
                    }

                    $this->info('Backup privado guardado em '.$disk.':'.$relative);

                    return self::SUCCESS;
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    unlink($temporary);
                }
            });
        } catch (Throwable $exception) {
            $this->error($replication->safeError($exception));

            return self::FAILURE;
        }
    }
}
