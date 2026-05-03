<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum TipoTramite: string
{
    case Deposito = 'Depósito';
    case Donacion = 'Donación';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
