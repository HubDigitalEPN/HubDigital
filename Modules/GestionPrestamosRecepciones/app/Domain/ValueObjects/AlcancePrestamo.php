<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum AlcancePrestamo: string
{
    case Nacional = 'nacional';
    case Internacional = 'internacional';

    public function equals(self $other): bool
    {
        return $this === $other;
    }

    public function esInternacional(): bool
    {
        return $this === self::Internacional;
    }
}
