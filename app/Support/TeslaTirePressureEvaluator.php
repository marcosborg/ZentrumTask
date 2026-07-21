<?php

namespace App\Support;

use App\Models\TeslaVehicleSnapshot;

class TeslaTirePressureEvaluator
{
    public const MINIMUM_PSI = 42.0;

    public const MAXIMUM_PSI = 43.0;

    public const MAXIMUM_DIFFERENCE_PSI = 1.0;

    private const PSI_PER_BAR = 14.5038;

    /**
     * @return array{status: 'compliant'|'abnormal'|'no_data', pressures: array{fl: float|null, fr: float|null, rl: float|null, rr: float|null}, difference: float|null, problems: list<string>}
     */
    public function evaluate(?TeslaVehicleSnapshot $snapshot): array
    {
        $rawPressures = [
            'fl' => $this->convertBarToPsi($snapshot?->tpms_pressure_fl),
            'fr' => $this->convertBarToPsi($snapshot?->tpms_pressure_fr),
            'rl' => $this->convertBarToPsi($snapshot?->tpms_pressure_rl),
            'rr' => $this->convertBarToPsi($snapshot?->tpms_pressure_rr),
        ];
        $pressures = array_map(
            fn (?float $pressure): ?float => $pressure === null ? null : round($pressure, 1),
            $rawPressures,
        );

        if (in_array(null, $rawPressures, true)) {
            return [
                'status' => 'no_data',
                'pressures' => $pressures,
                'difference' => null,
                'problems' => ['Nao foi possivel obter as quatro leituras de pressao.'],
            ];
        }

        $problems = [];
        $labels = [
            'fl' => 'dianteiro esquerdo',
            'fr' => 'dianteiro direito',
            'rl' => 'traseiro esquerdo',
            'rr' => 'traseiro direito',
        ];

        foreach ($rawPressures as $position => $pressure) {
            if ($pressure < self::MINIMUM_PSI || $pressure > self::MAXIMUM_PSI) {
                $problems[] = sprintf(
                    'Pneu %s com %.1f PSI, fora do intervalo de 42 a 43 PSI.',
                    $labels[$position],
                    $pressures[$position],
                );
            }
        }

        $difference = max($rawPressures) - min($rawPressures);

        if ($difference > self::MAXIMUM_DIFFERENCE_PSI) {
            $problems[] = sprintf('Diferenca de %.1f PSI entre o pneu com maior e menor pressao.', $difference);
        }

        return [
            'status' => $problems === [] ? 'compliant' : 'abnormal',
            'pressures' => $pressures,
            'difference' => round($difference, 1),
            'problems' => $problems,
        ];
    }

    public function barToPsi(mixed $pressure): ?float
    {
        $psi = $this->convertBarToPsi($pressure);

        return $psi === null ? null : round($psi, 1);
    }

    private function convertBarToPsi(mixed $pressure): ?float
    {
        return is_numeric($pressure) ? (float) $pressure * self::PSI_PER_BAR : null;
    }
}
