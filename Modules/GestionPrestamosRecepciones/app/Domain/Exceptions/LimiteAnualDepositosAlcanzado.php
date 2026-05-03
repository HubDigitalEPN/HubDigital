<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

final class LimiteAnualDepositosAlcanzado extends \DomainException
{
    public static function paraInvestigador(string $investigadorId): self
    {
        return new self(
            sprintf(
                'Límite anual de depósitos alcanzado para el investigador "%s". Máximo permitido: 3 depósitos por año.',
                $investigadorId
            )
        );
    }
}
