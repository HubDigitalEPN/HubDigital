<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa;

/**
 * Input DTO para consultar los documentos de un acta y autorizar el acceso.
 */
final readonly class ConsultarDocumentoActaInput
{
    /**
     * @param string $actaId Identificador del acta.
     * @param string $usuarioId Identificador del usuario que solicita el documento.
     * @param bool $esCurador Indica si el usuario tiene rol de curador.
     */
    public function __construct(
        public string $actaId,
        public string $usuarioId,
        public bool $esCurador,
    ) {}
}
