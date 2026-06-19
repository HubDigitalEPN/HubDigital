<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento;

/**
 * Input DTO para consultar el documento completo de un acta de préstamo.
 */
final readonly class ConsultarActaDocumentoInput
{
    /**
     * @param string $actaId Identificador del acta de préstamo.
     */
    public function __construct(
        public string $actaId,
    ) {}
}
