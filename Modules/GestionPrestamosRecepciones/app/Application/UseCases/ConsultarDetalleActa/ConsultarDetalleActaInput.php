<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleActa;

/**
 * Input DTO para consultar el detalle de un acta junto con el estado de su préstamo.
 */
final readonly class ConsultarDetalleActaInput
{
    /**
     * @param string $actaId Identificador del acta de préstamo.
     */
    public function __construct(
        public string $actaId,
    ) {}
}
