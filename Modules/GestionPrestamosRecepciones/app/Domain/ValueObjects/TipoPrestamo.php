<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum TipoPrestamo: string
{
    case Temporal = 'temporal';
    case Permanente = 'permanente';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
