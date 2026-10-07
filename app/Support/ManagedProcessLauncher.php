<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class ManagedProcessLauncher
{
    /** @param list<string> $arguments
     * @param  array<string, string>  $environment
     */
    public function runArtisan(array $arguments, array $environment = []): int
    {
        $process = new Process([(string) config('database-management.php_binary'), base_path('artisan'), ...$arguments], base_path(), $environment);
        $process->setTimeout(60);
        $process->run();

        return $process->getExitCode() ?? 1;
    }

    /** @param list<string> $arguments */
    public function launch(array $arguments, string $log): void
    {
        $command = [(string) config('database-management.php_binary'), base_path('artisan'), ...$arguments];

        if (DIRECTORY_SEPARATOR === '\\') {
            $quote = static fn (string $value): string => "'".str_replace("'", "''", $value)."'";
            $argumentString = implode(' ', array_map(
                static fn (string $value): string => '"'.str_replace('"', '\\"', $value).'"',
                array_slice($command, 1)
            ));
            $script = 'Start-Process -WindowStyle Hidden -FilePath '.$quote($command[0])
                .' -ArgumentList '.$quote($argumentString).' -WorkingDirectory '.$quote(base_path())
                .' -RedirectStandardOutput '.$quote($log).' -RedirectStandardError '.$quote($log.'.error');
            $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script]);
        } else {
            $process = new Process(['sh', '-c', 'nohup '.implode(' ', array_map(escapeshellarg(...), $command))
                .' > '.escapeshellarg($log).' 2>&1 < /dev/null &']);
        }

        $process->setTimeout(15);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Nao foi possivel iniciar o processo de gestao. Verifique as permissoes do PHP e os logs privados.');
        }
    }
}
