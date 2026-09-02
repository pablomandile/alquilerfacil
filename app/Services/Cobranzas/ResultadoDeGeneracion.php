<?php

namespace App\Services\Cobranzas;

use App\Models\Contract;
use App\Models\RentCharge;

readonly class ResultadoDeGeneracion
{
    private function __construct(
        public Contract $contract,
        public ?RentCharge $cargo,
        public bool $nuevo,
        public ?string $error = null,
    ) {}

    public static function creado(Contract $contract, RentCharge $cargo): self
    {
        return new self($contract, $cargo, true);
    }

    public static function yaExistia(Contract $contract, RentCharge $cargo): self
    {
        return new self($contract, $cargo, false);
    }

    public static function fallo(Contract $contract, string $error): self
    {
        return new self($contract, null, false, $error);
    }

    public function exitoso(): bool
    {
        return $this->error === null;
    }
}
