<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Orden de acción correctiva que se emite para el investigador cuando la recepción
 * del lote se suspende por una anomalía subsanable.
 */
enum AccionCorrectiva: string
{
    case CorreccionEnSitio = 'Corrección en sitio';
    case ReemplazoDeMaterial = 'Reemplazo de material';
    case AuditoriaDeInventario = 'Auditoría de inventario';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
