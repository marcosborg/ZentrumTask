<?php

namespace App\Support;

class DatabaseReplicationResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $message,
        public readonly string $title,
    ) {}

    public static function success(string $message, string $title = 'Copia concluida'): self
    {
        return new self(true, $message, $title);
    }

    public static function failure(string $message, string $title = 'Erro na copia da base de dados'): self
    {
        return new self(false, $message, $title);
    }
}
