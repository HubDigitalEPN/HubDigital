<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Resultado de comparar el nombre del perfil con el del documento de identidad:
 * conforme, discrepancia tipográfica (corregible) o discrepancia de tercero
 * (requiere justificación). Lo devuelve {@see \Modules\GestionPrestamosRecepciones\Domain\Services\ReglaValidacionIdentidad}.
 */
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
