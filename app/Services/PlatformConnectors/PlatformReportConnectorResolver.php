<?php

namespace App\Services\PlatformConnectors;

use InvalidArgumentException;

class PlatformReportConnectorResolver
{
    public function __construct(
        protected BoltFileSystemReportConnector $boltConnector,
        protected UberFileSystemReportConnector $uberConnector,
        protected PopFileSystemReportConnector $popConnector,
    ) {}

    public function resolve(string $platform): PlatformReportConnector
    {
        return match ($platform) {
            'bolt' => $this->boltConnector,
            'uber' => $this->uberConnector,
            'pop' => $this->popConnector,
            default => throw new InvalidArgumentException('Plataforma nao suportada: '.$platform),
        };
    }
}
