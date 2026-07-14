<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Ítem evaluado en la lista de verificación de recepción del lote (p. ej. "Nivel de
 * alcohol" con resultado "Adecuado"). Es evidencia inmutable de la evaluación física.
 */
final readonly class ItemVerificacionRecepcion
{
    public function __construct(
        public string $item,
        public string $resultado,
    ) {}
}
