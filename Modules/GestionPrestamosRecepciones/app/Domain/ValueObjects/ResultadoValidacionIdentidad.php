<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum ResultadoValidacionIdentidad: string
{
    case Conforme = 'Conforme';
    case DiscrepanciaTypografica = 'Discrepancia (Tipográfica)';
    case DiscrepanciaTercero = 'Discrepancia (Tercero)';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
