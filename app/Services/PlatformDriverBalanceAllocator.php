<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\PlatformDriverBalance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformDriverBalanceAllocator
{
    /**
     * @return array{allocated:int, pending:int}
     */
    public function allocate(?string $platform = null): array
    {
        $allocated = 0;
        $pending = 0;

        PlatformDriverBalance::query()
            ->whereNull('driver_participation_id')
            ->when($platform, fn ($query) => $query->where('platform', $platform))
            ->orderBy('id')
            ->chunkById(200, function ($balances) use (&$allocated, &$pending): void {
                foreach ($balances as $balance) {
                    try {
                        $driver = $balance->driver_id ? Driver::query()->find($balance->driver_id) : $this->findDriver($balance->platform, $balance->driver_code);
                    } catch (RuntimeException $exception) {
                        $balance->forceFill(['allocation_error' => $exception->getMessage()])->saveQuietly();
                        $pending++;

                        continue;
                    }

                    if ($driver === null) {
                        $balance->forceFill(['allocation_error' => 'Motorista não identificado.'])->saveQuietly();
                        $pending++;
                        Log::info('Balance pendente sem driver', [
                            'balance_id' => $balance->id,
                            'platform' => $balance->platform,
                            'driver_code' => $balance->driver_code,
                        ]);

                        continue;
                    }

                    $participation = app(ParticipationService::class)->resolve($driver->id, $balance->period_start, $balance->period_end);
                    if (! $participation) {
                        $balance->forceFill(['driver_id' => $driver->id, 'allocation_error' => 'Participação inexistente, conflitante ou período incompatível.'])->saveQuietly();
                        $pending++;

                        continue;
                    }
                    $balance->forceFill(['driver_id' => $driver->id, 'driver_participation_id' => $participation->id, 'operation' => $participation->operation->value, 'allocation_error' => null])->save();
                    $allocated++;
                }
            });

        return [
            'allocated' => $allocated,
            'pending' => $pending,
        ];
    }

    private function findDriver(string $platform, string $driverCode): ?Driver
    {
        $normalizedCode = $this->normalizeCode($driverCode);

        if ($platform === 'bolt') {
            $column = 'bolt_driver_code';
        } elseif ($platform === 'uber') {
            $column = 'uber_driver_code';
        } else {
            throw new RuntimeException('Plataforma desconhecida: '.$platform);
        }

        $matches = Driver::query()
            ->whereNotNull($column)
            ->get(['id', $column])
            ->filter(fn (Driver $driver): bool => $this->normalizeCode((string) $driver->{$column}) === $normalizedCode)
            ->values();

        if ($matches->count() > 1) {
            Log::warning('Balance conflict: multiple drivers matched', [
                'platform' => $platform,
                'driver_code' => $driverCode,
            ]);

            throw new RuntimeException("Mais de um driver encontrado para {$platform}: {$driverCode}");
        }

        return $matches->first();
    }

    private function normalizeCode(string $code): string
    {
        return Str::lower(trim($code));
    }
}
