<?php

namespace App\Services\PlatformConnectors;

use RuntimeException;
use Symfony\Component\Process\Process;

class UberPlaywrightCollector
{
    /**
     * @return array{downloaded:int, files:array<int, string>, output:array<string, mixed>}
     */
    public function collect(
        bool $headed = false,
        int $maxDownloads = 1,
        int $timeoutSeconds = 180,
        int $manualAuthSeconds = 300,
    ): array {
        $scriptPath = resource_path('js/collectors/uber-reports-collector.mjs');

        if (! is_file($scriptPath)) {
            throw new RuntimeException('Script do coletor Uber nao encontrado em '.$scriptPath);
        }

        $outputDirectory = (string) config('services.platform_reports.uber.directory', 'storage/app/platform-reports/uber');

        $command = [
            'node',
            $scriptPath,
            '--output-dir',
            $outputDirectory,
            '--max-downloads',
            (string) max(1, $maxDownloads),
            '--timeout-seconds',
            (string) max(30, $timeoutSeconds),
            '--manual-auth-seconds',
            (string) max(30, $manualAuthSeconds),
        ];

        if ($headed) {
            $command[] = '--headed';
        }

        $process = new Process($command, base_path(), $this->collectorEnvironment());
        $process->setTimeout(max(60, $timeoutSeconds + 30));
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $stdOutput = trim($process->getOutput());
            $message = $errorOutput !== '' ? $errorOutput : $stdOutput;

            throw new RuntimeException(
                $message !== '' ? $message : 'Falha ao executar o coletor Uber.'
            );
        }

        $rawOutput = trim($process->getOutput());
        $decoded = json_decode($rawOutput, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Saida invalida do coletor Uber: '.$rawOutput);
        }

        $files = $decoded['files'] ?? [];
        if (! is_array($files)) {
            $files = [];
        }

        $normalizedFiles = array_values(array_filter(array_map(
            fn (mixed $file): ?string => is_string($file) ? $file : null,
            $files
        )));

        return [
            'downloaded' => (int) ($decoded['downloaded'] ?? count($normalizedFiles)),
            'files' => $normalizedFiles,
            'output' => $decoded,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function collectorEnvironment(): array
    {
        return array_filter([
            'UBER_COLLECTOR_LOGIN_URL' => (string) config('services.uber_collector.login_url', ''),
            'UBER_COLLECTOR_REPORTS_URL' => (string) config('services.uber_collector.reports_url', ''),
            'UBER_COLLECTOR_EMAIL' => (string) config('services.uber_collector.email', ''),
            'UBER_COLLECTOR_PASSWORD' => (string) config('services.uber_collector.password', ''),
            'UBER_COLLECTOR_OTP' => (string) config('services.uber_collector.otp', ''),
            'UBER_COLLECTOR_STORAGE_STATE' => (string) config('services.uber_collector.storage_state', ''),
            'UBER_COLLECTOR_USER_DATA_DIR' => (string) config('services.uber_collector.user_data_dir', ''),
        ], fn (string $value): bool => $value !== '');
    }
}
