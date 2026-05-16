<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum EstadoDocumental: string
{
    case Valido = 'Válido';
    case RequiereCorreccion = 'Requiere Corrección';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
