<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActa;

/**
 * Input DTO para consultar un acta de préstamo con los datos de su solicitud.
 */
final readonly class ConsultarActaInput
{
    /**
     * @param string $actaId Identificador del acta de préstamo.
     */
    public function __construct(
        public string $actaId,
    ) {}
}
